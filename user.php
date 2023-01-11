<?php

class User
{
    public $id;
    public $traits;

    function __construct()
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
            $this->id = 'jmadiot';
            $this->traits = ['nbBooking' => 4];
            $_SESSION['user'] = $this->export();
        } else {
            return $this->import($_SESSION['user']);
        }

        return $this;
    }

    public function retrieve()
    {
        return $this;
    }

    public function export()
    {
        return serialize($this);
    }

    public function import($serializedUser)
    {
        return unserialize($serializedUser);
    }
}
