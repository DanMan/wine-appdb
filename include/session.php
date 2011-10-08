<?php

/*
 * session.php - session handler functions
 * sessions are stored in memcached
 * http://www.danga.com/memcached/
 */

class session
{
    // defines
    var $_server;
    var $_expire;
    var $_db;
    var $name;
    var $msg;
    
    // create session object
    function session ($name, $server = "127.0.0.1", $expire = 2)
    {
        // set the connection server
        $this->_server = $server;
        
        // set the session and cookie expiration time in days (default 30 days)
        $this->_expire = (60 * 60 * 24 * $expire);

        // set name for this session
        $this->name = $name;

        // define options for sessions
        ini_set('session.name', $this->name);
        ini_set('session.use_cookies', true);
        ini_set('session.use_only_cookies', true);
        
        // setup session object
        session_set_save_handler(
                                 array(&$this, "_open"), 
                                 array(&$this, "_close"), 
                                 array(&$this, "_read"),
                                 array(&$this, "_write"), 
                                 array(&$this, "_destroy"), 
                                 array(&$this, "_gc")
                                );
        
        // default lifetime on session cookie
        session_set_cookie_params(
                                  $this->_expire,
                                  '/'
                                 );
        
        // start the loaded session
        session_start();
        
        // make sure we have a valid memcache server connection
        if (!$this->_db->getVersion())
        {
            trigger_error("Unable to Connect to Session Server", E_USER_ERROR);
        }
    }

    // register variables into session (dynamic load and save of vars)
    function register ($var)
    {
        global $$var;
        
        // load $var into memory
        if (isset($_SESSION[$var]))
            $$var = $_SESSION[$var];
        
        // store var into session
        $_SESSION[$var] =& $$var;
    }

    // destroy session
    function destroy ()
    {
        session_destroy();
    }

    // add alert message to buffer that will be displayed on the Next page view of the same user in html class
    function addmsg ($text, $color = "black")
    {
        if (!isset($_SESSION['_msg']))
            $_SESSION['_msg'] = array();
        $_SESSION['_msg'][] = array(
                                     'msg'   => $text,
                                     'color' => $color
                                    );
    }

    // add alert message that will be displayed on the current page output in html class
    function alert ($text, $color = "black")
    {
        $this->msg[] = array(
                             'msg'   => $text,
                             'color' => $color
                            );
    }

    // clear session messages
    function purgemsg ()
    {
        $this->msg[] = array();
        $_SESSION['_msg'][] = array();
    }

    // output msg_buffer and clear it.
    function dumpmsgbuffer ()
    {
        if (isset($_SESSION['_msg']) and is_array($_SESSION['_msg']))
        {
            foreach ($_SESSION['_msg'] as $alert)
            {
                $this->msg[] = $alert;
            }
        }
        $_SESSION['_msg'] = array();
    }

    // connect to session
    function _open ($save_path, $session_name)
    {
        $this->_db = new Memcache;
        return $this->_db->connect($this->_server, "11211");
    }

    // close the session
    function _close ()
    {
        return $this->_db->close();
    }

    // restore a session from memory
    function _read ($id)
    {
        return $this->_db->get($id);
    }

    // write the session
    function _write ($id, $data)
    {
        if ($this->_db->get($id))
        {
            $this->_db->replace($id, $data, null, $this->_expire);
        }
        else
        {
            $this->_db->set($id, $data, null, $this->_expire);
        }
        return true;
    }

    // Delete the Session
    function _destroy ($id)
    {
        return $this->_db->delete($id, 0);
    }

    // Garbage Collector (Not Needed for MemCache)
    function _gc ($maxlifetime)
    {
        return true;
    }

}
// end session

?>
