<?php

        $config = array(
            "username" => getenv('GLESYS_USERNAME'),      // Your username used to log on to http://customer.glesys.com
            "serverid" => getenv('GLESYS_SERVERID'),      // The ID of the server to be controlled. starts with vz or xm. Can be found when you
            "API-key" =>  getenv('GLESYS_APIKEY'),  				  // Your API-key. Can be generated at http://customer.glesys.com
            "peak" => array(
                "memory" => 768,    // Amount of memory assigned with the peak()-function.
                "cores" => 2        // Amount of CPU cores assigned with the peak()-function.
            ),
            "low" => array(
                "memory" => 256,    // Amount of memory assigned with the low()-function.
                "cores" => 1        // Amount of CPU cores assigned with the low()-function.
            ),
            "changevals" => array(  // Configs for the memchange()-function
                "lower" => 33,      // If less than this percentage of the memory is used, the memory will be downgraded
                "higher" => 67      // If the server uses more than "higher"% of the memory, the memory will be upgraded.
            )
        );
        include 'glesys_php_example/APIClient.php';
        try{
            $client = new APIClient($config["username"], $config["API-key"]);
        } catch(Exception $e){
            echo "Connection error: ".$e->getMessage();
            return;
        }

		$servercmd = $argv[1];
		
		
        if(isset($servercmd))
        {
                if($servercmd == "low" or $servercmd == "serverlow")
                    serverlow();
                elseif($servercmd == "peak" or $servercmd == "serverpeak")
                    serverpeak();
                elseif($servercmd == "reboot")
                    reboot();
                elseif($servercmd == "details" or $servercmd == "detail")
                    details();
                elseif($servercmd == "status")
                    status();
                elseif($servercmd == "memchange"or $servercmd == "changemem")
                    memchange();
                elseif($servercmd == "console"or $servercmd == "remote")
                    console();
                else
                    echo"Unknown command.";
        }
        else
        {
			echo "Do the setup yo\n";
 	
        }
        // Downgrade the memory and decreases the amount of CPU cores.
        // Used when low traffic towards the server is expected.
        // Amount of memory and CPU cores are edited in $config.
        function serverlow()
        {
            global $client;
            global $config;
	    try{
            	$client->post("server/edit", array("serverid" => $config["serverid"], "memorysize" => $config["low"]["memory"], "cpucores" => $config["low"]["cores"]));
	    } catch(Exception $e){
		echo "Serverlow error: ".$e->getMessage();
		return;
	    }
	    echo "Memory set to ".$config["low"]["memory"]."GB.<br/>\nCPU-cores are now ".$config["low"]["cores"] .".";
        }
        // Upgrades the memory and increases the amount of CPU cores.
        // Used when high traffic towards the server is expected.
        // Amount of memory and CPU cores are edited in the $config.
        function serverpeak()
        {
            global $client;
            global $config;
            try{
                $client->post("server/edit", array("serverid" => $config["serverid"], "memorysize" => $config["peak"]["memory"], "cpucores" => $config["peak"]["cores"]));
            } catch(Exception $e){
                echo "Serverpeak error: ".$e->getMessage();
                return;
            }
            echo "Memory set to ".$config["peak"]["memory"]."GB.<br/>\nCPU-cores are now ".$config["peak"]["cores"] .".";
        }
        // Reboots the server. Uses "stop" with the "type"="reboot"
        function reboot()
        {
            global $client;
            global $config;
            try{
                $client->post("server/stop", array("serverid" => $config["serverid"], "type" => "reboot"));
            } catch(Exception $e){
                echo "Connection error: ".$e->getMessage();
                return;
            }
            echo"The server was restarted succesfully.";
        }
	//Get information about the server. This lists information such as configuration (amount of cpu,memory and disk), ip adresses and more.
        function details()
        {
            global $client;
            global $config;
            try{
                $details = $client->post("server/details", array("serverid" => $config["serverid"]));
            } catch(Exception $e){
                echo "Details error: ".$e->getMessage();
                return;
            }
			print_r($details);
			exit;
            echo "<table>";
            foreach($details["server"] as $key => $values)
            {
                if($key == "disk" or $key == "transfer")
                    $values .= " GB";
                if($key == "memory")
                    $values .= " MB";
                if($key == "cost")
                    echo "<tr><td>cost</td><td>".$values["amount"].$values["currency"]."/".$values["timeperiod"]."</td></tr>";
                elseif($key == "iplist")
                {
                    echo "<tr><td>iplist</td><td>";
                    foreach($values as $newVal)
                        echo"(IPv".$newVal["version"].") ".$newVal["ip"]."<br/>";
                    echo "</td></tr>";
                }
                else
                    echo "<tr><td>".$key."</td><td>".$values."</td></tr>";
            }
            echo "</table>";
        }
	//Get status-information about the server. This information includes status (if the server is running or not),cpu usage, memory usage, uptime and more.
        function status()
        {
            global $client;
            global $config;
            try{
            $status = $client->post("server/status", array("serverid" => $config["serverid"]));
            } catch(Exception $e){
                echo "Status error: ".$e->getMessage();
                return;
            }
			print_r($status);
            foreach($status["server"] as $key => $values)
            {
                if($key == "cpu")
                   echo "cpu usage:      ".($values["system"]+$values["user"]+$values["nice"])."%\n";
                elseif($key == "memory")
                   echo "memory-usage:   ".$values["usage"]. "/".$values["max"]." ". $values["unit"]."\n";
                elseif($key == "disk")
                   echo "harddrive free: ".($values["max"]-$values["usage"])." ".$values["unit"]."\n";
                elseif($key == "transfer")
               	   echo "transfer:       " . $values["usage"] . "/" . $values["max"]. " " . $values["unit"] . "\n";
               elseif($key == "uptime")
            	   echo "uptime:         " . $values["current"] . " " . $values["unit"] ."\n";
/*                else
                    echo "".$key." : ".$values."";	*/

            }
        }
	//Automaticall upgrade or downgrade the memory of the server based on how much memory the server is currently using.
	//Memory usage is only available via the api for OpenVZ-servers. To upgrade/downgrade XEN-servers you have to
	//supply the amount of used memory in the usedmem get-variable.
        function memchange()
        {
            global $client;
            global $config;
            try{
            $freemem = $client->post("server/status", array("serverid" => $config["serverid"]));
            } catch(Exception $e){
                echo "Memchange error: ".$e->getMessage();
                return;
            }
            $allowed_options = array(128, 256, 512, 768, 1024, 1536, 2048, 2560, 3072, 3584, 4096, 5120, 6144, 7168, 8192, 9216, 10240, 11264, 12288);
			print_r($freemem);
            $size = $freemem["server"]["memory"]["max"];
            if(isset($_GET["usedmem"]))
                if(is_numeric($_GET["usedmem"]))
                    $freemem = $size - $_GET["usedmem"];
                else
                {
                    echo"\"usedmem\" is an invalid value. Must be a number.";
                    die;
                }
            else
                if(is_numeric($freemem["server"]["memory"]["usage"]))
                    $freemem = $size-$freemem["server"]["memory"]["usage"];
                else
                {
                    echo"Needs the \"usedmem\" argument.";
                    die;
                }
            $i = 0;
            while($allowed_options[$i] != $size)
	    {
                $i++;
            }

            if($freemem > $size*(1-($config["changevals"]["lower"]/100)) and $i > 0)
            {
                $client->post("server/edit", array("serverid" => $config["serverid"], "memorysize" => $available[$i-1]));
                echo"Memory degraded to ".$available[$i-1]." MB since less than ".$config["changevals"]["lower"]." percent of the memory was used.";
            }
            elseif($freemem < $size*(1-($config["changevals"]["higher"]/100)) and $i < 18)
            {
                $client->post("server/edit", array("serverid" => $config["serverid"], "memorysize" => $available[$i+1]));
                echo"Memory upgraded to ".$available[$i+1]." MB since more than ".$config["changevals"]["higher"]." percent of the memory was used.";
            }
            else
                echo "Memory seems to \"fit\"";
        }
	//Get information about how to connect to this server using VNC.
        function console()
        {
            global $client;
            global $config;
            try{
            $console = $client->post("server/console", array("serverid" => $config["serverid"]));
            } catch(Exception $e){
                echo "Console error: ".$e->getMessage();
                return;
            }
            echo"Remote access information:<br/>IP: ".$console["remote"]["host"]."<br/>Port: ".$console["remote"]["port"]."<br/>Password: ".$console["remote"]["password"];
        }
        ?>
