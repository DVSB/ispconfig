<?php
/*
Copyright (c) 2007-2008, Till Brehm, projektfarm Gmbh and Oliver Vogel www.muv.com
All rights reserved.

Redistribution and use in source and binary forms, with or without modification,
are permitted provided that the following conditions are met:

    * Redistributions of source code must retain the above copyright notice,
      this list of conditions and the following disclaimer.
    * Redistributions in binary form must reproduce the above copyright notice,
      this list of conditions and the following disclaimer in the documentation
      and/or other materials provided with the distribution.
    * Neither the name of ISPConfig nor the names of its contributors
      may be used to endorse or promote products derived from this software without
      specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT,
INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY
OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING
NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE,
EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/

class monitor_core_module {
    /* TODO: this should be a config - var instead of a "constant" */
    var $interval = 5; // do the monitoring every 5 minutes

    var $module_name = 'monitor_core_module';
    var $class_name = 'monitor_core_module';
    /* No actions at this time. maybe later... */
    var $actions_available = array();

    //* This function is called during ispconfig installation to determine
	//  if a symlink shall be created for this plugin.
	function onInstall() {
		global $conf;
		
		return true;
		
	}
	
	/*
        This function is called when the module is loaded
    */
    function onLoad() {
        global $app;

        /*
        Annonce the actions that where provided by this module, so plugins
        can register on them.
        */
        /* none at them moment */
        //$app->plugins->announceEvents($this->module_name,$this->actions_available);

        /*
        As we want to get notified of any changes on several database tables,
        we register for them.

        The following function registers the function "functionname"
            to be executed when a record for the table "dbtable" is
            processed in the sys_datalog. "classname" is the name of the
            class that contains the function functionname.
        */
        /* none at them moment */
        //$app->modules->registerTableHook('mail_access','mail_module','process');

        /*
        Do the monitor every n minutes and write the result in the db
        */
        $min = date('i');
        if (($min % $this->interval) == 0)
        {
            $this->doMonitor();
        }
    }

    /*
     This function is called when a change in one of the registered tables is detected.
     The function then raises the events for the plugins.
    */
    function process($tablename, $action, $data) {
        //		global $app;
        //
        //		switch ($tablename) {
        //			case 'mail_access':
        //				if($action == 'i') $app->plugins->raiseEvent('mail_access_insert',$data);
        //				if($action == 'u') $app->plugins->raiseEvent('mail_access_update',$data);
        //				if($action == 'd') $app->plugins->raiseEvent('mail_access_delete',$data);
        //				break;
        //		} // end switch
    } // end function

    /*
    This method is called every n minutes, when the module ist loaded.
    The method then does a system-monitoring
    */
    // TODO: what monitoring is done should be a config-var
    function doMonitor()
    {
        /* Calls the single Monitoring steps */
        $this->monitorServer();
        $this->monitorDiskUsage();
        $this->monitorMemUsage();
        $this->monitorCpu();
        $this->monitorServices();
        $this->monitorMailLog();
        $this->monitorMailWarnLog();
        $this->monitorMailErrLog();
        $this->monitorMessagesLog();
        $this->monitorISPCCronLog();
        $this->monitorFreshClamLog();
        $this->monitorClamAvLog();
        $this->monitorIspConfigLog();
        $this->monitorSystemUpdate();
        $this->monitorMailQueue();
        $this->monitorRaid();
        $this->monitorRkHunter();
		$this->monitorFail2ban();
        $this->monitorSysLog();
    }

    function monitorServer(){
        global $app;
        global $conf;

        /* the id of the server as int */
        $server_id = intval($conf["server_id"]);

        /** The type of the data */
        $type = 'server_load';

        /*
        Fetch the data into a array
        */
        $procUptime = shell_exec("cat /proc/uptime | cut -f1 -d' '");
        $data['up_days'] = floor($procUptime/86400);
        $data['up_hours'] = floor(($procUptime-$data['up_days']*86400)/3600);
        $data['up_minutes'] = floor(($procUptime-$data['up_days']*86400-$data['up_hours']*3600)/60);

        $data['uptime'] = shell_exec("uptime");

        $tmp = explode(",", $data['uptime'], 4);
        $tmpUser = explode(" ", trim($tmp[2]));
        $data['user_online'] = intval($tmpUser[0]);
		
		/* Old Load Average Code
        $loadTmp = explode(":" , trim($tmp[3]));
        $load = explode(",",  $loadTmp[1]);
        $data['load_1'] = floatval(trim($load[0]));
        $data['load_5'] = floatval(trim($load[1]));
        $data['load_15'] = floatval(trim($load[2])); */

		//* New Load Average code to fix "always zero" bug in non-english distros. NEEDS TESTING
		$loadTmp = shell_exec("cat /proc/loadavg | cut -f1-3 -d' '");
		$load = explode(" ", $loadTmp);
		$data['load_1'] = floatval(str_replace(',', '.', $load[0]));
		$data['load_5'] = floatval(str_replace(',', '.', $load[1]));
		$data['load_15'] = floatval(str_replace(',', '.', $load[2]));

        /** The state of the server-load. */
        $state = 'ok';
        if ($data['load_1'] > 20 ) $state = 'info';
        if ($data['load_1'] > 50 ) $state = 'warning';
        if ($data['load_1'] > 100 ) $state = 'critical';
        if ($data['load_1'] > 150 ) $state = 'error';

        /*
        Insert the data into the database
        */
        $sql = "INSERT INTO monitor_data (server_id, type, created, data, state) " .
            "VALUES (".
        $server_id . ", " .
            "'" . $app->dbmaster->quote($type) . "', " .
        time() . ", " .
            "'" . $app->dbmaster->quote(serialize($data)) . "', " .
            "'" . $state . "'" .
            ")";
        $app->dbmaster->query($sql);

        /* The new data is written, now we can delete the old one */
        $this->_delOldRecords($type, 10);
    }

    function monitorDiskUsage() {
        global $app;
        global $conf;

        /* the id of the server as int */
        $server_id = intval($conf["server_id"]);

        /** The type of the data */
        $type = 'disk_usage';

        /** The state of the disk-usage */
        $state = 'ok';

        /** Fetch the data of ALL devices into a array (needed for monitoring!)*/
        $dfData = shell_exec("df -hT");

        // split into array
        $df = explode("\n", $dfData);

        /*
         * ignore the first line, process the rest
         */
        for($i=1; $i <= sizeof($df); $i++){
            if ($df[$i] != '')
            {
                /*
                 * Make a array of the data
                 */
                $s = preg_split ("/[\s]+/", $df[$i]);
                $data[$i]['fs'] = $s[0];
                $data[$i]['type'] = $s[1];
                $data[$i]['size'] = $s[2];
                $data[$i]['used'] = $s[3];
                $data[$i]['available'] = $s[4];
                $data[$i]['percent'] = $s[5];
                $data[$i]['mounted'] = $s[6];
                /*
                 * calculate the state
                 */
                $usePercent = floatval($data[$i]['percent']);
				
				//* We dont want to check the cdrom drive as a cd / dvd is always 100% full
				if($data[$i]['type'] != 'iso9660' && $data[$i]['type'] != 'cramfs' && $data[$i]['type'] != 'udf') {
                	if ($usePercent > 75) $state = $this->_setState($state, 'info');
                	if ($usePercent > 80) $state = $this->_setState($state, 'warning');
                	if ($usePercent > 90) $state = $this->_setState($state, 'critical');
                	if ($usePercent > 95) $state = $this->_setState($state, 'error');
				}
            }
        }


        /*
        Insert the data into the database
        */
        $sql = "INSERT INTO monitor_data (server_id, type, created, data, state) " .
            "VALUES (".
        $server_id . ", " .
            "'" . $app->dbmaster->quote($type) . "', " .
        time() . ", " .
            "'" . $app->dbmaster->quote(serialize($data)) . "', " .
            "'" . $state . "'" .
            ")";
        $app->dbmaster->query($sql);

        /* The new data is written, now we can delete the old one */
        $this->_delOldRecords($type, 10);
    }


    function monitorMemUsage()
    {
        global $app;
        global $conf;

        /* the id of the server as int */
        $server_id = intval($conf["server_id"]);

        /** The type of the data */
        $type = 'mem_usage';

        /*
        Fetch the data into a array
        */
        $miData = shell_exec("cat /proc/meminfo");

        $memInfo = explode("\n", $miData);

        foreach($memInfo as $line){
            $part = preg_split("/:/", $line);
            $key = trim($part[0]);
            $tmp = explode(" ", trim($part[1]));
            $value = 0;
            if ($tmp[1] == 'kB') $value = $tmp[0] * 1024;
            $data[$key] = $value;
        }

        /*
         * actually this info has no state.
         * maybe someone knows better...???...
         */
        $state = 'no_state';

        /*
        Insert the data into the database
        */
        $sql = "INSERT INTO monitor_data (server_id, type, created, data, state) " .
            "VALUES (".
        $server_id . ", " .
            "'" . $app->dbmaster->quote($type) . "', " .
        time() . ", " .
            "'" . $app->dbmaster->quote(serialize($data)) . "', " .
            "'" . $state . "'" .
            ")";
        $app->dbmaster->query($sql);

        /* The new data is written, now we can delete the old one */
        $this->_delOldRecords($type, 10);
    }


    function monitorCpu()
    {
        global $app;
        global $conf;

        /* the id of the server as int */
        $server_id = intval($conf["server_id"]);

        /** The type of the data */
        $type = 'cpu_info';

        /*
        Fetch the data into a array
        */
        $cpuData = shell_exec("cat /proc/cpuinfo");
        $cpuInfo = explode("\n", $cpuData);
		$processor = 0;

        foreach($cpuInfo as $line){
            
			$part = preg_split("/:/", $line);
            $key = trim($part[0]);
            $value = trim($part[1]);
			if($key == 'processor') $processor = intval($value);
            if($key != '') $data[$key.' '.$processor] = $value;
        }

        /* the cpu has no state. It is, what it is */
        $state = 'no_state';

        /*
        Insert the data into the database
        */
        $sql = "INSERT INTO monitor_data (server_id, type, created, data, state) " .
            "VALUES (".
        $server_id . ", " .
            "'" . $app->dbmaster->quote($type) . "', " .
        time() . ", " .
            "'" . $app->dbmaster->quote(serialize($data)) . "', " .
            "'" . $state . "'" .
            ")";
        $app->dbmaster->query($sql);

        /* The new data is written, now we can delete the old one */
        $this->_delOldRecords($type, 10);
    }


    function monitorServices()
    {
        global $app;
        global $conf;

        /** the id of the server as int */
        $server_id = intval($conf["server_id"]);

        /** get the "active" Services of the server from the DB */
        $services = $app->dbmaster->queryOneRecord("SELECT * FROM server WHERE server_id = " . $server_id);

        /* The type of the Monitor-data */
        $type = 'services';

        /** the State of the monitoring */
        /* ok, if ALL aktive services are running,
         * error, if not
         * There is no other state!
         */
        $state = 'ok';

        /* Monitor Webserver */
        $data['webserver'] = -1; // unknown - not needed
        if ($services['web_server'] == 1)
        {
            if($this->_checkTcp('localhost', 80)) {
                $data['webserver'] = 1;
            } else {
                $data['webserver'] = 0;
                $state = 'error'; // because service is down
            }
        }

        /* Monitor FTP-Server */
        $data['ftpserver'] = -1; // unknown - not needed
        if ($services['file_server'] == 1)
        {
            if($this->_checkFtp('localhost', 21)) {
                $data['ftpserver'] = 1;
            } else {
                $data['ftpserver'] = 0;
                $state = 'error'; // because service is down
            }
        }

        /* Monitor SMTP-Server */
        $data['smtpserver'] = -1; // unknown - not needed
        if ($services['mail_server'] == 1)
        {
            if($this->_checkTcp('localhost', 25)) {
                $data['smtpserver'] = 1;
            } else {
                $data['smtpserver'] = 0;
                $state = 'error'; // because service is down
            }
        }

        /* Monitor POP3-Server */
        $data['pop3server'] = -1; // unknown - not needed
        if ($services['mail_server'] == 1)
        {
            if($this->_checkTcp('localhost', 110)) {
                $data['pop3server'] = 1;
            } else {
                $data['pop3server'] = 0;
                $state = 'error'; // because service is down
            }
        }

        /* Monitor IMAP-Server */
        $data['imapserver'] = -1; // unknown - not needed
        if ($services['mail_server'] == 1)
        {
            if($this->_checkTcp('localhost', 143)) {
                $data['imapserver'] = 1;
            } else {
                $data['imapserver'] = 0;
                $state = 'error'; // because service is down
            }
        }

        /* Monitor BIND-Server */
        $data['bindserver'] = -1; // unknown - not needed
        if ($services['dns_server'] == 1)
        {
            if($this->_checkTcp('localhost', 53)) {
                $data['bindserver'] = 1;
            } else {
                $data['bindserver'] = 0;
                $state = 'error'; // because service is down
            }
        }

        /* Monitor MYSQL-Server */
        $data['mysqlserver'] = -1; // unknown - not needed
        if ($services['db_server'] == 1)
        {
            if($this->_checkTcp('localhost', 3306)) {
                $data['mysqlserver'] = 1;
            } else {
                $data['mysqlserver'] = 0;
                $state = 'error'; // because service is down
            }
        }


        /*
        Insert the data into the database
        */
        $sql = "INSERT INTO monitor_data (server_id, type, created, data, state) " .
            "VALUES (".
        $server_id . ", " .
            "'" . $app->dbmaster->quote($type) . "', " .
        time() . ", " .
            "'" . $app->dbmaster->quote(serialize($data)) . "', " .
            "'" . $state . "'" .
            ")";
        $app->dbmaster->query($sql);

        /* The new data is written, now we can delete the old one */
        $this->_delOldRecords($type, 10);
    }


    function monitorSystemUpdate(){
        /*
         *  This monitoring is expensive, so do it only once a hour
         */
        $min = date('i');
        if ($min != 0) return;

        /*
         * OK - here we go...
         */
        global $app;
        global $conf;

        /* the id of the server as int */
        $server_id = intval($conf["server_id"]);

        /** The type of the data */
        $type = 'system_update';

        /* This monitoring is only available at debian or Ubuntu */
        if(file_exists('/etc/debian_version')){

            /*
             * first update the "update-database"
             */
            shell_exec('apt-get update');

            /*
             * Then test the upgrade.
             * if there is any output, then there is a needed update
             */
            $aptData = shell_exec('apt-get -s -qq dist-upgrade');
            if ($aptData == '')
            {
                /* There is nothing to update! */
                $state = 'ok';
            }
            else
            {
                /* There is something to update! */
                $state = 'warning';
            }

            /*
             * Fetch the output
             */
            $data['output'] = shell_exec('apt-get -s -q dist-upgrade');
        }
        elseif (file_exists("/etc/gentoo-release")) {
        	
        	/*
        	 * first update the portage tree
        	 */
        	
        	// In keeping with gentoo's rsync policy, don't update to frequently (every four hours - taken from http://www.gentoo.org/doc/en/source_mirrors.xml)
        	$do_update = true;
        	if (file_exists('/usr/portage/metadata/timestamp.chk'))
        	{
        		$datetime = file_get_contents('/usr/portage/metadata/timestamp.chk');
        		$datetime = trim($datetime);
        		
        		$dstamp = strtotime($datetime);
        		if ($dstamp) 
        		{
        			$checkat = $dstamp + 14400; // + 4hours
        			if (mktime() < $checkat) {
        				$do_update = false;
        			} 
        		}
        	}
        	
        	if ($do_update) {
        		shell_exec('emerge --sync --quiet');
        	}
        	
        	/*
             * Then test the upgrade.
             * if there is any output, then there is a needed update
             */
            $emergeData = shell_exec('emerge -puDNt --color n --nospinner --quiet world');
        	if ($emergeData == '')
            {
                /* There is nothing to update! */
                $state = 'ok';
            }
            else
            {
                /* There is something to update! */
                $state = 'warning';
            }
            
            /*
             * Fetch the output
             */
            $data['output'] = shell_exec('emerge -pvuDNt --color n --nospinner world');
        }
        else {
            /*
             * It is not debian/Ubuntu, so there is no data and no state
             *
             * no_state, NOT unknown, because "unknown" is shown as state
             * inside the GUI. no_state is hidden.
             *
             * We have to write NO DATA inside the DB, because the GUI
             * could not know, if there is any dat, or not...
             */
            $state = 'no_state';
            $data['output']= '';
        }

        /*
         * Insert the data into the database
         */
        $sql = "INSERT INTO monitor_data (server_id, type, created, data, state) " .
            "VALUES (".
        $server_id . ", " .
            "'" . $app->dbmaster->quote($type) . "', " .
        time() . ", " .
            "'" . $app->dbmaster->quote(serialize($data)) . "', " .
            "'" . $state . "'" .
            ")";
        $app->dbmaster->query($sql);

        /* The new data is written, now we can delete the old one */
        $this->_delOldRecords($type, 0, 2);
    }

    function monitorMailQueue(){
        global $app;
        global $conf;

        /* the id of the server as int */
        $server_id = intval($conf["server_id"]);

        /** The type of the data */
        $type = 'mailq';

        /* Get the data from the mailq */
        $data['output'] = shell_exec('mailq');

        /*
         *  The last line has more informations
         */
        $tmp = explode("\n", $data['output']);
        $more = $tmp[sizeof($tmp) - 1];
        $this->_getIntArray($more);
        $data['bytes'] = $res[0];
        $data['requests'] = $res[1];

        /** The state of the mailq. */
        $state = 'ok';
        if ($data['requests'] > 2000 ) $state = 'info';
        if ($data['requests'] > 5000 ) $state = 'warning';
        if ($data['requests'] > 8000 ) $state = 'critical';
        if ($data['requests'] > 10000 ) $state = 'error';

        /*
         * Insert the data into the database
         */
        $sql = "INSERT INTO monitor_data (server_id, type, created, data, state) " .
            "VALUES (".
        $server_id . ", " .
            "'" . $app->dbmaster->quote($type) . "', " .
        time() . ", " .
            "'" . $app->dbmaster->quote(serialize($data)) . "', " .
            "'" . $state . "'" .
            ")";
        $app->dbmaster->query($sql);

        /* The new data is written, now we can delete the old one */
        $this->_delOldRecords($type, 10);
    }


    function monitorRaid(){
        global $app;
        global $conf;

        /* the id of the server as int */
        $server_id = intval($conf["server_id"]);

        /** The type of the data */
        $type = 'raid_state';

        /* This monitoring is only available if mdadm is installed */
        $location = system('which mdadm', $retval);
        if($retval === 0){
            /*
             * Fetch the output
             */
            $data['output'] = shell_exec('cat /proc/mdstat');

            /*
             * Then calc the state.
             */
            $tmp = explode("\n", $data['output']);
            $state = 'ok';
            for ($i = 0; $i < sizeof($tmp); $i++){
                /* fetch the next line */
                $line = $tmp[$i];

                if ((strpos($line, '[U_]') !== false) || (strpos($line, '[_U]') !== false))
                {
                    /* One Disk is not working.
                     * if the next line starts with "[>" or "[=" then
                     * recovery (resync) is in state and the state is
                     * information instead of critical
                     */
                    $nextLine = $tmp[$i+1];
                    if ((strpos($nextLine, '[>') === false) && (strpos($nextLine, '[=') === false)) {
                        $state = $this->_setState($state, 'critical');
                    }
                    else
                    {
                        $state = $this->_setState($state, 'info');
                    }
                }
                if (strpos($line, '[__]') !== false)
                {
                    /* both Disk are not working */
                    $state = $this->_setState($state, 'error');
                }
                if (strpos($line, '[UU]') !== false)
                {
                    /* The disks are OK.
                     * if the next line starts with "[>" or "[=" then
                     * recovery (resync) is in state and the state is
                     * information instead of ok
                     */
                    $nextLine = $tmp[$i+1];
                    if ((strpos($nextLine, '[>') === false) && (strpos($nextLine, '[=') === false)) {
                        $state = $this->_setState($state, 'ok');
                    }
                    else
                    {
                        $state = $this->_setState($state, 'info');
                    }
                }
            }

        }
        else {
            /*
             * mdadm is not installed, so there is no data and no state
             *
             * no_state, NOT unknown, because "unknown" is shown as state
             * inside the GUI. no_state is hidden.
             *
             * We have to write NO DATA inside the DB, because the GUI
             * could not know, if there is any dat, or not...
             */
            $state = 'no_state';
            $data['output']= '';
        }

        /*
         * Insert the data into the database
         */
        $sql = "INSERT INTO monitor_data (server_id, type, created, data, state) " .
            "VALUES (".
        $server_id . ", " .
            "'" . $app->dbmaster->quote($type) . "', " .
        time() . ", " .
            "'" . $app->dbmaster->quote(serialize($data)) . "', " .
            "'" . $state . "'" .
            ")";
        $app->dbmaster->query($sql);

        /* The new data is written, now we can delete the old one */
        $this->_delOldRecords($type, 10);
    }

    function monitorRkHunter(){
        /*
         *  This monitoring is expensive, so do it only once a day
         */
        $min = date('i');
        $hour = date('H');
        if (!($min == 0 && $hour == 23)) return;

        global $app;
        global $conf;

        /* the id of the server as int */
        $server_id = intval($conf["server_id"]);

        /** The type of the data */
        $type = 'rkhunter';

        /* This monitoring is only available if rkhunter is installed */
        $location = system('which rkhunter', $retval);
        if($retval === 0){
            /*
             * Fetch the output
             */
            $data['output'] = shell_exec('rkhunter --update --checkall --nocolors --skip-keypress');

            /*
             * At this moment, there is no state (maybe later)
             */
            $state = 'no_state';
        }
        else {
            /*
             * rkhunter is not installed, so there is no data and no state
             *
             * no_state, NOT unknown, because "unknown" is shown as state
             * inside the GUI. no_state is hidden.
             *
             * We have to write NO DATA inside the DB, because the GUI
             * could not know, if there is any dat, or not...
             */
            $state = 'no_state';
            $data['output']= '';
        }

        /*
         * Insert the data into the database
         */
        $sql = "INSERT INTO monitor_data (server_id, type, created, data, state) " .
            "VALUES (".
        $server_id . ", " .
            "'" . $app->dbmaster->quote($type) . "', " .
        time() . ", " .
            "'" . $app->dbmaster->quote(serialize($data)) . "', " .
            "'" . $state . "'" .
            ")";
        $app->dbmaster->query($sql);

        /* The new data is written, now we can delete the old one */
        $this->_delOldRecords($type, 0, 2);
    }

    function monitorFail2ban(){
        global $app;
        global $conf;

        /* the id of the server as int */
        $server_id = intval($conf["server_id"]);

        /** The type of the data */
        $type = 'log_fail2ban';

        /* This monitoring is only available if fail2ban is installed */
        $location = system('which fail2ban-client', $retval); // Debian, Ubuntu, Fedora
		if($retval !== 0) $location = system('which fail2ban', $retval); // CentOS
        if($retval === 0){
			/*  Get the data of the log */
			$data = $this->_getLogData($type);

            /*
             * At this moment, there is no state (maybe later)
             */
            $state = 'no_state';
        }
        else {
            /*
             * fail2ban is not installed, so there is no data and no state
             *
             * no_state, NOT unknown, because "unknown" is shown as state
             * inside the GUI. no_state is hidden.
             *
             * We have to write NO DATA inside the DB, because the GUI
             * could not know, if there is any dat, or not...
             */
            $state = 'no_state';
            $data = '';
        }

        /*
         * Insert the data into the database
         */
        $sql = "INSERT INTO monitor_data (server_id, type, created, data, state) " .
            "VALUES (".
        $server_id . ", " .
            "'" . $app->dbmaster->quote($type) . "', " .
        time() . ", " .
            "'" . $app->dbmaster->quote(serialize($data)) . "', " .
            "'" . $state . "'" .
            ")";
        $app->dbmaster->query($sql);

        /* The new data is written, now we can delete the old one */
        $this->_delOldRecords($type, 10);
    }

    function monitorSysLog(){
        global $app;
        global $conf;

        /* the id of the server as int */
        $server_id = intval($conf["server_id"]);

        /** The type of the data */
        $type = 'sys_log';

		/*
		 * is there any warning or error for this server?
		 */
		$state = 'ok';
        $dbData = $app->dbmaster->queryAllRecords("SELECT loglevel FROM sys_log WHERE server_id = " . $server_id . " AND loglevel > 0");
		if (is_array($dbData)) {
		    foreach($dbData as $item){
			if ($item['loglevel'] == 1) $state = $this->_setState($state, 'warning');
			if ($item['loglevel'] == 2) $state = $this->_setState($state, 'error');
		    }
		}

		/** There is no monitor-data because the data is in the sys_log table */
        $data['output']= '';

        /*
         * Insert the data into the database
         */
        $sql = "INSERT INTO monitor_data (server_id, type, created, data, state) " .
            "VALUES (".
        $server_id . ", " .
            "'" . $app->dbmaster->quote($type) . "', " .
        time() . ", " .
            "'" . $app->dbmaster->quote(serialize($data)) . "', " .
            "'" . $state . "'" .
            ")";
        $app->dbmaster->query($sql);

        /* The new data is written, now we can delete the old one */
        $this->_delOldRecords($type, 10);
    }

	function monitorMailLog()
    {
        global $app;
        global $conf;

        /* the id of the server as int */
        $server_id = intval($conf["server_id"]);

        /** The type of the data */
        $type = 'log_mail';

        /* Get the data of the log */
        $data = $this->_getLogData($type);

        /*
         * actually this info has no state.
         * maybe someone knows better...???...
         */
        $state = 'no_state';

        /*
        Insert the data into the database
        */
        $sql = "INSERT INTO monitor_data (server_id, type, created, data, state) " .
            "VALUES (".
        $server_id . ", " .
            "'" . $app->dbmaster->quote($type) . "', " .
        time() . ", " .
            "'" . $app->dbmaster->quote(serialize($data)) . "', " .
            "'" . $state . "'" .
            ")";
        $app->dbmaster->query($sql);

        /* The new data is written, now we can delete the old one */
        $this->_delOldRecords($type, 10);
    }

    function monitorMailWarnLog()
    {
        global $app;
        global $conf;

        /* the id of the server as int */
        $server_id = intval($conf["server_id"]);

        /** The type of the data */
        $type = 'log_mail_warn';

        /* Get the data of the log */
        $data = $this->_getLogData($type);

        /*
         * actually this info has no state.
         * maybe someone knows better...???...
         */
        $state = 'no_state';

        /*
        Insert the data into the database
        */
        $sql = "INSERT INTO monitor_data (server_id, type, created, data, state) " .
            "VALUES (".
        $server_id . ", " .
            "'" . $app->dbmaster->quote($type) . "', " .
        time() . ", " .
            "'" . $app->dbmaster->quote(serialize($data)) . "', " .
            "'" . $state . "'" .
            ")";
        $app->dbmaster->query($sql);

        /* The new data is written, now we can delete the old one */
        $this->_delOldRecords($type, 10);
    }

    function monitorMailErrLog()
    {
        global $app;
        global $conf;

        /* the id of the server as int */
        $server_id = intval($conf["server_id"]);

        /** The type of the data */
        $type = 'log_mail_err';

        /* Get the data of the log */
        $data = $this->_getLogData($type);

        /*
         * actually this info has no state.
         * maybe someone knows better...???...
         */
        $state = 'no_state';

        /*
        Insert the data into the database
        */
        $sql = "INSERT INTO monitor_data (server_id, type, created, data, state) " .
            "VALUES (".
        $server_id . ", " .
            "'" . $app->dbmaster->quote($type) . "', " .
        time() . ", " .
            "'" . $app->dbmaster->quote(serialize($data)) . "', " .
            "'" . $state . "'" .
            ")";
        $app->dbmaster->query($sql);

        /* The new data is written, now we can delete the old one */
        $this->_delOldRecords($type, 10);
    }


    function monitorMessagesLog()
    {
        global $app;
        global $conf;

        /* the id of the server as int */
        $server_id = intval($conf["server_id"]);

        /** The type of the data */
        $type = 'log_messages';

        /* Get the data of the log */
        $data = $this->_getLogData($type);

        /*
         * actually this info has no state.
         * maybe someone knows better...???...
         */
        $state = 'no_state';

        /*
        Insert the data into the database
        */
        $sql = "INSERT INTO monitor_data (server_id, type, created, data, state) " .
            "VALUES (".
        $server_id . ", " .
            "'" . $app->dbmaster->quote($type) . "', " .
        time() . ", " .
            "'" . $app->dbmaster->quote(serialize($data)) . "', " .
            "'" . $state . "'" .
            ")";
        $app->dbmaster->query($sql);

        /* The new data is written, now we can delete the old one */
        $this->_delOldRecords($type, 10);
    }

    function monitorISPCCronLog()
    {
        global $app;
        global $conf;

        /* the id of the server as int */
        $server_id = intval($conf["server_id"]);

        /** The type of the data */
        $type = 'log_ispc_cron';

        /* Get the data of the log */
        $data = $this->_getLogData($type);

        /*
         * actually this info has no state.
         * maybe someone knows better...???...
         */
        $state = 'no_state';

        /*
        Insert the data into the database
        */
        $sql = "INSERT INTO monitor_data (server_id, type, created, data, state) " .
            "VALUES (".
        $server_id . ", " .
            "'" . $app->dbmaster->quote($type) . "', " .
        time() . ", " .
            "'" . $app->dbmaster->quote(serialize($data)) . "', " .
            "'" . $state . "'" .
            ")";
        $app->dbmaster->query($sql);

        /* The new data is written, now we can delete the old one */
        $this->_delOldRecords($type, 10);
    }
    
    function monitorFreshClamLog()
    {
        global $app;
        global $conf;

        /* the id of the server as int */
        $server_id = intval($conf["server_id"]);

        /** The type of the data */
        $type = 'log_freshclam';

        /* Get the data of the log */
        $data = $this->_getLogData($type);

        /* Get the data from the LAST log-Entry.
         * if there can be found:
         * WARNING: Your ClamAV installation is OUTDATED!
         * then the clamav is outdated. This is a warning!
         */
        $state = 'ok';

        $tmp = explode("\n", $data);
        $lastLog = array();
        if ($tmp[sizeof($tmp)-1] == "")
        {
            /* the log ends with an empty line remove this */
            array_pop($tmp);
        }
        if (strpos($tmp[sizeof($tmp)-1], "-------------") !== false)
        {
            /* the log ends with "-----..." remove this */
            array_pop($tmp);
        }
        for ($i = sizeof($tmp) -1; $i > 0; $i--){
            if (strpos($tmp[$i], "---------") === false){
                /* no delimiter found, so add this to the last-log */
                $lastLog[] = $tmp[$i];
            }
            else
            {
                /* delimiter found, so there is no more line left! */
                break;
            }
        }

        /*
         * Now we have the last log in the array.
         * Check if the outdated-string is found...
         */
        foreach($lastLog as $line){
            if (strpos(strtolower($line), "outdated") !== false) {
                 $state = $this->_setState($state, 'warning');
            }
        }

        /*
        Insert the data into the database
        */
        $sql = "INSERT INTO monitor_data (server_id, type, created, data, state) " .
            "VALUES (".
        $server_id . ", " .
            "'" . $app->dbmaster->quote($type) . "', " .
        time() . ", " .
            "'" . $app->dbmaster->quote(serialize($data)) . "', " .
            "'" . $state . "'" .
            ")";
        $app->dbmaster->query($sql);

        /* The new data is written, now we can delete the old one */
        $this->_delOldRecords($type, 10);
    }

    function monitorClamAvLog()
    {
        global $app;
        global $conf;

        /* the id of the server as int */
        $server_id = intval($conf["server_id"]);

        /** The type of the data */
        $type = 'log_clamav';

        /* Get the data of the log */
        $data = $this->_getLogData($type);

        // Todo: the state should be calculated.
        $state = 'ok';

        /*
        Insert the data into the database
        */
        $sql = "INSERT INTO monitor_data (server_id, type, created, data, state) " .
            "VALUES (".
        $server_id . ", " .
            "'" . $app->dbmaster->quote($type) . "', " .
        time() . ", " .
            "'" . $app->dbmaster->quote(serialize($data)) . "', " .
            "'" . $state . "'" .
            ")";
        $app->dbmaster->query($sql);

        /* The new data is written, now we can delete the old one */
        $this->_delOldRecords($type, 10);
    }

    function monitorIspConfigLog()
    {
        global $app;
        global $conf;

        /* the id of the server as int */
        $server_id = intval($conf["server_id"]);

        /** The type of the data */
        $type = 'log_ispconfig';

        /* Get the data of the log */
        $data = $this->_getLogData($type);

        // Todo: the state should be calculated.
        $state = 'ok';

        /*
        Insert the data into the database
        */
        $sql = "INSERT INTO monitor_data (server_id, type, created, data, state) " .
            "VALUES (".
        $server_id . ", " .
            "'" . $app->dbmaster->quote($type) . "', " .
        time() . ", " .
            "'" . $app->dbmaster->quote(serialize($data)) . "', " .
            "'" . $state . "'" .
            ")";
        $app->dbmaster->query($sql);

        /* The new data is written, now we can delete the old one */
        $this->_delOldRecords($type, 10);
    }


    function _getLogData($log){
        
		$dist = '';
		$logfile = '';
		
		if(@is_file('/etc/debian_version')) $dist = 'debian';
		if(@is_file('/etc/redhat-release')) $dist = 'redhat';
		if(@is_file('/etc/SuSE-release')) $dist = 'suse';
		if(@is_file('/etc/gentoo-release')) $dist = 'gentoo';
		
		switch($log) {
            case 'log_mail':
                if($dist == 'debian') $logfile = '/var/log/mail.log';
				if($dist == 'redhat') $logfile = '/var/log/maillog';
				if($dist == 'suse') $logfile = '/var/log/mail.info';
				if($dist == 'gentoo') $logfile = '/var/log/maillog';
                break;
            case 'log_mail_warn':
                if($dist == 'debian') $logfile = '/var/log/mail.warn';
				if($dist == 'redhat') $logfile = '/var/log/maillog';
				if($dist == 'suse') $logfile = '/var/log/mail.warn';
				if($dist == 'gentoo') $logfile = '/var/log/maillog';
                break;
            case 'log_mail_err':
                if($dist == 'debian') $logfile = '/var/log/mail.err';
				if($dist == 'redhat') $logfile = '/var/log/maillog';
				if($dist == 'suse') $logfile = '/var/log/mail.err';
				if($dist == 'gentoo') $logfile = '/var/log/maillog';
                break;
            case 'log_messages':
                if($dist == 'debian') $logfile = '/var/log/messages';
				if($dist == 'redhat') $logfile = '/var/log/messages';
				if($dist == 'suse') $logfile = '/var/log/messages';
				if($dist == 'gentoo') $logfile = '/var/log/messages';
                break;
            case 'log_ispc_cron':
                if($dist == 'debian') $logfile = '/var/log/ispconfig/cron.log';
				if($dist == 'redhat') $logfile = '/var/log/ispconfig/cron.log';
				if($dist == 'suse') $logfile = '/var/log/ispconfig/cron.log';
				if($dist == 'gentoo') $logfile = '/var/log/cron';
                break;
            case 'log_freshclam':
                if($dist == 'debian') $logfile = '/var/log/clamav/freshclam.log';
				if($dist == 'redhat') $logfile = (is_file('/var/log/clamav/freshclam.log') ? '/var/log/clamav/freshclam.log' : '/var/log/freshclam.log');
                if($dist == 'suse') $logfile = '';
                if($dist == 'gentoo') $logfile = '/var/log/clamav/freshclam.log';
				break;
            case 'log_clamav':
                if($dist == 'debian') $logfile = '/var/log/clamav/clamav.log';
				if($dist == 'redhat') $logfile = (is_file('/var/log/clamav/clamd.log') ? '/var/log/clamav/clamd.log' : '/var/log/maillog');
				if($dist == 'suse') $logfile = '';
				if($dist == 'gentoo') $logfile = '/var/log/clamav/clamd.log';
                break;
            case 'log_fail2ban':
                if($dist == 'debian') $logfile = '/var/log/fail2ban.log';
				if($dist == 'redhat') $logfile = '/var/log/fail2ban.log';
				if($dist == 'suse') $logfile = '/var/log/fail2ban.log';
				if($dist == 'gentoo') $logfile = '/var/log/fail2ban.log';
                break;
            case 'log_ispconfig':
                if($dist == 'debian') $logfile = '/var/log/ispconfig/ispconfig.log';
				if($dist == 'redhat') $logfile = '/var/log/ispconfig/ispconfig.log';
				if($dist == 'suse') $logfile = '/var/log/ispconfig/ispconfig.log';
				if($dist == 'gentoo') $logfile = '/var/log/ispconfig/ispconfig.log';
                break;
            default:
                $logfile = '';
                break;
        }

        // Getting the logfile content
        if($logfile != '') {
            $logfile = escapeshellcmd($logfile);
            if(stristr($logfile, ';') or substr($logfile,0,9) != '/var/log/' or stristr($logfile, '..')) {
                $log = 'Logfile path error.';
            }
            else
            {
                $log = '';
                if(is_readable($logfile)) {
                    if($fd = popen("tail -n 100 $logfile", 'r')) {
                        while (!feof($fd)) {
                            $log .= fgets($fd, 4096);
                            $n++;
                            if($n > 1000) break;
                        }
                        fclose($fd);
                    }
                } else {
                    $log = 'Unable to read '.$logfile;
                }
            }
        }

        return $log;
    }

    function _checkTcp ($host,$port) {

        $fp = @fsockopen ($host, $port, $errno, $errstr, 2);

        if ($fp) {
            fclose($fp);
            return true;
        } else {
            return false;
        }
    }

    function _checkUdp ($host,$port) {

        $fp = @fsockopen ('udp://'.$host, $port, $errno, $errstr, 2);

        if ($fp) {
            fclose($fp);
            return true;
        } else {
            return false;
        }
    }

    function _checkFtp ($host,$port){

        $conn_id = @ftp_connect($host, $port);

        if($conn_id){
            @ftp_close($conn_id);
            return true;
        } else {
            return false;
        }
    }

    /*
     Deletes Records older than n.
    */
    function _delOldRecords($type, $min, $hour=0, $days=0) {
        global $app;

        $now = time();
        $old = $now - ($min * 60) - ($hour * 60 * 60) - ($days * 24 * 60 * 60);
        $sql = "DELETE FROM monitor_data " .
            "WHERE " .
            "type =" . "'" . $app->dbmaster->quote($type) . "' " .
            "AND " .
            "created < " . $old;
        $app->dbmaster->query($sql);
    }

    /*
     * Set the state to the given level (or higher, but not lesser).
     * * If the actual state is critical and you call the method with ok,
     *   then the state is critical.
     *
     * * If the actual state is critical and you call the method with error,
     *   then the state is error.
     */
    function _setState($oldState, $newState)
    {
        /*
         * Calculate the weight of the old state
         */
        switch ($oldState) {
            case 'no_state': $oldInt = 0;
                break;
            case 'ok': $oldInt = 1;
                break;
            case 'unknown': $oldInt = 2;
                break;
            case 'info': $oldInt = 3;
                break;
            case 'warning': $oldInt = 4;
                break;
            case 'critical': $oldInt = 5;
                break;
            case 'error': $oldInt = 6;
                break;
        }
        /*
         * Calculate the weight of the new state
         */
        switch ($newState) {
            case 'no_state': $newInt = 0 ;
                break;
            case 'ok': $newInt = 1 ;
                break;
            case 'unknown': $newInt = 2 ;
                break;
            case 'info': $newInt = 3 ;
                break;
            case 'warning': $newInt = 4 ;
                break;
            case 'critical': $newInt = 5 ;
                break;
            case 'error': $newInt = 6 ;
                break;
        }

        /*
         * Set to the higher level
         */
        if ($newInt > $oldInt){
            return $newState;
        }
        else
        {
            return $oldState;
        }
    }

    function _getIntArray($line){
        /** The array of float found */
        $res = array();
        /* First build a array from the line */
        $data = explode(' ', $line);
        /* then check if any item is a float */
        foreach ($data as $item) {
            if ($item . '' == (int)$item . ''){
                $res[] = $item;
            }
        }
        return $res;
    }


} // end class

?>