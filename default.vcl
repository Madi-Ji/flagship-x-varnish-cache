vcl 4.1;
# Based on: https://github.com/mattiasgeniar/varnish-4.0-configuration-templates/blob/master/default.vcl
# Forked : https://github.com/jnerin/varnish-4.0-configuration-templates/blob/master/default.vcl

import std; 
import directors;
backend server1 { # Define one backend
	.host = "php"; # IP or Hostname of backend
	.port = "80"; # Port Apache or whatever is listening
	.max_connections = 10; # That's it
	.probe = {
	   # We prefer to only do a HEAD
		.request = 
			"HEAD /ping.php HTTP/1.1"
			"Host: localhost"
			"User-Agent: Varnish; Health Check"
			"Connection: close";      	
		.interval = 200s; # check the health of each backend every X seconds
		.timeout = 1s; # timing out after 1 second.
		# If 3 out of the last 5 polls succeeded the backend is considered healthy, otherwise it will be marked as sick
		.window = 5;
		.threshold = 3;
		}
	.first_byte_timeout     = 300s;   # How long to wait before we receive a first byte from our backend?
	.connect_timeout        = 5s;     # How long to wait for a backend connection?
	.between_bytes_timeout  = 2s;     # How long to wait between bytes received from our backend?
}
acl purge {
# ACL we'll use later to allow purges
	"localhost";
	"127.0.0.1";
	"::1";
}

sub vcl_init {
# Called when VCL is loaded, before any requests pass through it. Typically used to initialize VMODs.
	new vdir = directors.round_robin();
	vdir.add_backend(server1);
	# vdir.add_backend(serverN);
}

sub vcl_recv {

	set req.backend_hint = vdir.backend(); # send all traffic to the vdir director

	if (req.restarts == 0) {
		if (req.http.X-Forwarded-For) { # set or append the client.ip to X-Forwarded-For header
			set req.http.X-Forwarded-For = req.http.X-Forwarded-For + ", " + client.ip;
		} else {
			set req.http.X-Forwarded-For = client.ip;
		}
	}

	# Normalize the header, remove the port (in case you're testing this on various TCP ports)
	set req.http.Host = regsub(req.http.Host, ":[0-9]+", "");
	
	# Normalize the query arguments
	set req.url = std.querysort(req.url);

	if (req.method == "REFRESH") {
	if (!client.ip ~ purge) { # purge is the ACL defined at the begining
		# Not from an allowed IP? Then die with an error.
		return (synth(405, "This IP is not allowed to send REFRESH requests."));
	}
	# If you got this stage (and didn't error out above), purge the cached result
			set req.method = "GET";
			set req.hash_always_miss = true;
	}

	if (req.method == "BAN") {
	# See https://www.varnish-software.com/static/book/Cache_invalidation.html#smart-bans
		if (!client.ip ~ purge) { # purge is the ACL defined at the begining
			# Not from an allowed IP? Then die with an error.
			return (synth(405, "This IP is not allowed to send BAN requests."));
		}
		# If you got this stage (and didn't error out above), purge the cached result

                ban("obj.http.x-url ~ " + req.http.x-ban-url +
                    " && obj.http.x-host ~ " + req.http.x-ban-host);
                return (synth(200, "Banned"));

        }


	# Only deal with "normal" types
	if (req.method != "GET" &&
			req.method != "HEAD" &&
			req.method != "PUT" &&
			req.method != "POST" &&
			req.method != "TRACE" &&
			req.method != "OPTIONS" &&
			req.method != "PATCH" &&
			req.method != "DELETE") {
		/* Non-RFC2616 or CONNECT which is weird. */
		return (pipe);
	}

	

	# Implementing websocket support (https://www.varnish-cache.org/docs/4.0/users-guide/vcl-example-websockets.html)
	if (req.http.Upgrade ~ "(?i)websocket") {
		return (pipe);
	}

	# Some generic URL manipulation, useful for all templates that follow
	# First remove the Google Analytics added parameters, useless for our backend
	if (req.url ~ "(\?|&)(utm_source|utm_medium|utm_campaign|utm_content|gclid|cx|ie|cof|siteurl)=") {
		set req.url = regsuball(req.url, "&(utm_source|utm_medium|utm_campaign|utm_content|gclid|cx|ie|cof|siteurl)=([A-z0-9_\-\.%25]+)", "");
		set req.url = regsuball(req.url, "\?(utm_source|utm_medium|utm_campaign|utm_content|gclid|cx|ie|cof|siteurl)=([A-z0-9_\-\.%25]+)", "?");
		set req.url = regsub(req.url, "\?&", "?");
		set req.url = regsub(req.url, "\?$", "");
	}

	# Strip hash, server doesn't need it.
	if (req.url ~ "\#") {
		set req.url = regsub(req.url, "\#.*$", "");
	}

	# Strip a trailing ? if it exists
	if (req.url ~ "\?$") {
		set req.url = regsub(req.url, "\?$", "");
	}

	# Are there cookies left with only spaces or that are empty?
	if (req.http.cookie ~ "^\s*$") {
		unset req.http.cookie;
	}

	# FS_recv
	# Method GET - The visitor main interaction and very first.
	if(req.method == "GET" && req.http.Cookie !~ "fs-experiences=ignore-me@optout") {

		# Known FS (Cookie)
			# Parse Cookie
			# Header exist
		std.log("FS KNOWN");
		if(req.http.Cookie ~ "fs-experiences"){
			std.log("PARSE COOKIES");
			# Original Regex => [aA-zZ\d\-]+@(([aA-zZ\d]+:[aA-zZ\d]+;?)+)
			# With value URLencoded => ([aA-zZ\d\-]+)[@|%40](([aA-zZ\d]+[:|%3A][aA-zZ\d]+[\||%7C]?)+) => /1 visitorID + /2 Exp-Cache-Key
			set req.http.x-fs-experiences = regsub(req.http.Cookie, "(.*?)(fs-experiences=)([^;]*)(.*)$", "\3"); # Trunk Cookie to only fs_experiences cookie content
			if(req.http.x-fs-experiences == regsub(req.http.x-fs-experiences, "([aA-zZ\d\-]+)[@|%40](([0-9a-v]{20}+[:|%3A][0-9a-v]{20}+[\||%7C]?)+)", "\2")) {
				std.log("[FS] WARNING: Cookie malformed");
				set req.http.x-fs-visitor = "ignore-me";
				set req.http.x-fs-experiences = "optout";
			} else {
				set req.http.x-fs-visitor = regsub(req.http.x-fs-experiences, "([aA-zZ\d\-]+)[@|%40](([0-9a-v]{20}+[:|%3A][0-9a-v]{20}+[\||%7C]?)+)", "\1"); # Capture VisitorID
				set req.http.x-fs-experiences = regsub(req.http.x-fs-experiences, "([aA-zZ\d\-]+)[@|%40](([0-9a-v]{20}+[:|%3A][0-9a-v]{20}+[\||%7C]?)+)", "\2"); # Capture [CacheKey]
			}
			std.log("HEADER AVAILABLE");
		}

		# Unknown FS
			# Pass then restart
		std.log("FS UNKNOWN");
		if(!req.http.x-fs-experiences) {
			if(req.restarts == 0) { # security, set restart N
				std.log("READY FOR RESTART");
				# Giving the signal
				set req.http.x-fs-take-decision = "0";
				return (pass); # See you in #2 OR RESTART ?
			}
		}


		# Restart x-make-decision + header || Headers exist
			# Optout -
			# Continue
		if(req.http.x-fs-take-decision || req.http.x-fs-experiences){
			if(req.http.x-fs-experiences == "optout"){
				std.log("OPTOUT: true");
				unset req.http.x-fs-visitor;
				unset req.http.x-fs-experiences;
			}
		}
		
	}
	# END FS_recv

	return (hash);
}

sub vcl_hash {

	if (req.http.host) {
		hash_data(req.http.host);
	} else {
		hash_data(server.ip);
	}

	hash_data(req.url);
	# FS_hash
	if(req.http.x-fs-experiences){
		std.log("LOOK IN CACHE w/ FS");
		hash_data(req.http.x-fs-experiences);
	}
	# END FS_hash

	return (lookup);
}


sub vcl_backend_fetch {
    if (bereq.method == "GET" || bereq.method == "HEAD") {
        unset bereq.body;
    }
	# FS_be_fetch
	if(bereq.http.x-fs-take-decision){
		if(bereq.http.x-fs-take-decision == "0") {
			set bereq.method = "HEAD";
		}
		if(bereq.http.x-fs-take-decision == "1") {
			set bereq.method = "GET";
		}
		unset bereq.http.x-fs-take-decision;
	}
	# END FS_be_fetch
	
    return (fetch);
}

sub vcl_deliver {
	# DEBUG Only
	set resp.http.x-restart = req.restarts;

	if(req.http.x-fs-take-decision){
		if(req.http.x-fs-take-decision == "0" && req.restarts == 0) {
			set req.method = "GET";
			set req.http.x-fs-visitor = resp.http.x-fs-visitor;
			set req.http.x-fs-experiences = resp.http.x-fs-experiences;
			set req.http.x-fs-take-decision = "1";
			std.log("RESTART HEAD");
			return (restart);
		} 
		if((req.http.x-fs-take-decision == "1" && req.http.x-fs-experiences)) {
			std.log("BECOMING KNOWN FS - Set Cookie");
			if (resp.http.Set-Cookie) {
				set resp.http.Set-Cookie = resp.http.Set-Cookie + "fs-experiences="+req.http.x-fs-visitor+"@"+req.http.x-fs-experiences+"; path=/; domain="+req.http.Host;
			} else {
				set resp.http.Set-Cookie = "fs-experiences="+req.http.x-fs-visitor+"@"+req.http.x-fs-experiences+"; path=/; domain="+req.http.Host;
			}
		}
	}

	if (obj.hits > 0) {
		set resp.http.X-Cache = "HIT";
	} else {
		set resp.http.X-Cache = "MISS";
	}

	return (deliver);
}