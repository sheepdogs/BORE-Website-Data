<?php

//Set Variables
$settings = parse_ini_file("Settings.ini");

$dbuser = $settings["Username"];
$dbpass = $settings["Password"];
$dbhost = 'localhost';
$dbname = 'data_bni';

$leadershipwebhook = $settings["Leadership"];
$moonswebhook = $settings["Moons"];

$t1addresses = $settings["Tier1Addresses"];
$t2addresses = $settings["Tier2Addresses"];
$subject = "BORE Alliance Alert";

$laststructure = "";

//Get Data
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) {
	printf("Failed to connect to MySQL: " . mysqli_connect_error());
}

$sql = "SELECT text,type FROM data_notifs WHERE notified IS NULL AND type IN('StructureUnderAttack','WarDeclared','MoonminingLaserFired','MoonminingAutomaticFracture','StructureLostShields','MercOfferedNegotiationMsg','StructureFuelAlert','CorpWarInvalidatedMsg','CorpWarRetractedMsg','CorpWarSurrenderMsg')";

$data = mysqli_query($conn, $sql);

if (!$data) {
	printf("Errormessage: %s\n", mysqli_error($conn));
}

//Iterate through rows
foreach($data as $row) {

	//Format Message and create variables
	$text = $row['text'];
	$type = $row['type'];
	$orecounter = 1;

	//Structure Attack
	if($type == "StructureUnderAttack") {
		$lines = explode(PHP_EOL, $text);
		foreach($lines as $line) {
			if(strpos($line, "allianceName") === 0) {
				$alliancename = ", a member of ".explode(": ",$line)[1].",";
			}
			if(strpos($line, "corpName") === 0) {
                                $corpname = explode(": ",$line)[1];
                        }
			if(strpos($line, "shieldPercentage") === 0) {
                                $shieldpercentage = explode(": ",$line)[1];
                        }
			if(strpos($line, "armorPercentage") === 0) {
                                $armorpercentage = explode(": ",$line)[1];
                        }
			if(strpos($line, "hullPercentage") === 0) {
                                $hullpercentage = explode(": ",$line)[1];
                        }
                        if(strpos($line, "structureID") === 0) {
                                $structureid = explode(" ",$line)[2];

				//Get Structure Name
				$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
				if (!$conn) {
				        printf("Failed to connect to MySQL: " . mysqli_connect_error());
				}

				$sql = "SELECT structurename FROM data_structures WHERE structureid = ".$structureid." LIMIT 1";

				$structuredata = mysqli_fetch_assoc(mysqli_query($conn, $sql));

				if (!$structuredata) {
				        printf("Errormessage: %s\n", mysqli_error($conn));
				}
				$structurename = $structuredata['structurename'];
                        }
		}
		//Make the Body
	        $body = $corpname.$alliancename." has attacked ".$structurename."!\r\n";
        	$body = $body."Shields: ".number_format($shieldpercentage,0)."%\r\n";
	        $body = $body."Armor: ".number_format($armorpercentage,0)."%\r\n";
	        $body = $body."Hull: ".number_format($hullpercentage,0)."%\r\n";
	}

	//Corp War Dec
	if($type == "WarDeclared") {
                $lines = explode(PHP_EOL, $text);
                foreach($lines as $line) {
			if(strpos($line, "againstID") === 0) {
                                $againstid = explode(": ",$line)[1].",";
                        }
			if(strpos($line, "declaredByID") === 0) {
                                $declaredbyid = explode(": ",$line)[1];

				if(substr($declaredbyid,0,2)=="98"){
					// Lets get the corp data with a GET
					$remote_url = "https://esi.evetech.net/v4/corporations/$declaredbyid/";

					$opts = array(
					  'http' => array(
					    'method' => 'GET',
					    'header' => array(
					         "User-Agent: ...",
					         "Host: esi.evetech.net"
					    ),
					  )
					);

					$context = stream_context_create($opts);

					// Parse the results into an array
					$result = file_get_contents($remote_url, false, $context);
					$corpdata = json_decode($result, true);
					$corpname = $corpdata['name'];
				}
				if(substr($declaredbyid,0,2)=="99"){
                                        // Lets get the alliance data with a GET
                                        $remote_url = "https://esi.evetech.net/v3/alliances/$declaredbyid/";

                                        $opts = array(
                                          'http' => array(
                                            'method' => 'GET',
                                            'header' => array(
                                                 "User-Agent: ...",
                                                 "Host: esi.evetech.net"
                                            ),
                                          )
                                        );

					$context = stream_context_create($opts);

					// Parse the results into an array
					$result = file_get_contents($remote_url, false, $context);
					$alliancedata = json_decode($result, true);
					$alliancename = $alliancedata['name'];
                                }
                        }
			if(strpos($line, "warHQ:") === 0) {
				$warhq = explode(": ",$line)[1];
				$warhq = str_replace("<b>","",$warhq);
				$warhq = str_replace("</b>","",$warhq);
			}
		}
		//Make the Body
		if($againstid = "98583004") {
			if(substr($declaredbyid,0,2)=="98") {
				$body = "War declaration by ".$corpname."!\r\n";
				$body = $body."War HQ: ".$warhq."!\r\n";
				$body = $body."https://zkillboard.com/corporation/".$declaredbyid;
			}
			if(substr($declaredbyid,0,2)=="99") {
				$body = "War declaration by ".$alliancename."!\r\n";
				$body = $body."War HQ: ".$warhq."!\r\n";
				$body = $body."https://zkillboard.com/alliance/".$declaredbyid;
			}
		}
	}

	//Moon Mining Laser Fired
	if($type == "MoonminingLaserFired" || $type == "MoonminingAutomaticFracture") {
		$lines = explode(PHP_EOL, $text);
                foreach($lines as $line) {
			//Fired By
			if(strpos($line, "firedBy:") === 0) {
                                $firedbyid = explode(": ",$line)[1];

				// Lets get the corp data with a GET
                                $remote_url = "https://esi.evetech.net/v4/characters/$firedbyid/";

                                $opts = array(
                                	'http' => array(
                                        'method' => 'GET',
                                        'header' => array(
                                        	"User-Agent: ...",
                                                "Host: esi.evetech.net"
                                      	),
                                 	)
                                );

                                $context = stream_context_create($opts);

                                // Parse the results into an array
                                $result = file_get_contents($remote_url, false, $context);
                                $firedbydata = json_decode($result, true);
                                $firedbyname = $firedbydata['name'];

                        }
			//Structure Name
			if(strpos($line, "structureName") === 0) {
                                $structurename = str_replace("Shastal - ", "", explode(": ",$line)[1]);
                        }
			//Moon ID
			if(strpos($line, "moonID") === 0) {
				$moonid = explode(": ",$line)[1];

				//Get Moon Name
                                $conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
                                if (!$conn) {
                                        printf("Failed to connect to MySQL: " . mysqli_connect_error());
                                }

                                $sql = "SELECT moonname FROM data_moons WHERE moonid = ".$moonid." LIMIT 1";

                                $moondata = mysqli_fetch_assoc(mysqli_query($conn, $sql));

				if (!$moondata) {
                                        printf("Errormessage: %s\n", mysqli_error($conn));
                                }
                                $moonname = $moondata['moonname'];
			}
			//Ore Data
			if(strpos($line, "  4") === 0) {
				$oreamount = explode(" ",$line)[3];
                                $oreid = str_replace(":", "", explode(" ",$line)[2]);

				//Get Ore Name
                                $conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
                                if (!$conn) {
                                        printf("Failed to connect to MySQL: " . mysqli_connect_error());
                                }

                                $sql = "SELECT ore, SUM(isk_per_m3) as 'iskperm3' FROM view_ore_ref_mineral_isk WHERE oretypeid = ".$oreid." GROUP BY ore LIMIT 1";

                                $oredata = mysqli_fetch_assoc(mysqli_query($conn, $sql));

                                if (!$oredata) {
                                        printf("Errormessage: %s\n", mysqli_error($conn));
                                }
                                $orename = $oredata['ore'];
				$orevalue = $oredata['iskperm3'];

				if($orecounter == 1) {
					$ore1body = $orename.": ".number_format($oreamount,0)." m3 - ".number_format($oreamount * $orevalue,0)." ISK\r\n";
					$oretotal = $oreamount;
					$orevaluetotal = $orevalue * $oreamount;
				}
				if($orecounter == 2) {
                                        $ore2body = $orename.": ".number_format($oreamount,0)." m3 - ".number_format($oreamount * $orevalue,0)." ISK\r\n";
                                }
				if($orecounter == 3) {
                                        $ore3body = $orename.": ".number_format($oreamount,0)." m3 - ".number_format($oreamount * $orevalue,0)." ISK\r\n";
                                }
				if($orecounter == 4) {
                                        $ore4body = $orename.": ".number_format($oreamount,0)." m3 - ".number_format($oreamount * $orevalue,0)." ISK\r\n";
                                }

				if($orecounter > 1) {
					$oretotal = $oretotal + $oreamount;
					$orevaluetotal = $orevaluetotal + ($orevalue * $oreamount);
				}
				$orecounter = $orecounter + 1;
                        }
		}
		if($type == "MoonminingAutomaticFracture") {
			$body = $structurename." at ".$moonname." has fracked! **AUTOMATIC**"."\r\n";
		} else {
			$body = $structurename." at ".$moonname." has fracked! Thank you ".$firedbyname."!\r\n";
		}
		if($ore1body != "") {
			$body = $body.$ore1body;
		}
		if($ore2body != "") {
                        $body = $body.$ore2body;
                }
		if($ore3body != "") {
                        $body = $body.$ore3body;
                }
		if($orecounter == 5) {
                        $body = $body.$ore4body;
                }
		$body = $body."\r\nTotal: ".number_format($oretotal,0)." m3 - ".number_format($orevaluetotal,0)." ISK";
	}

	//Structure Lost Shields
	if($type == "StructureLostShields") {
        	$lines = explode(PHP_EOL, $text);
                foreach($lines as $line) {
			//Structure Name
			if(strpos($line, "structureID") === 0) {
                                $structureid = explode(" ",$line)[2];

                                //Get Structure Name
                                $conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
                                if (!$conn) {
                                        printf("Failed to connect to MySQL: " . mysqli_connect_error());
                                }

                                $sql = "SELECT structurename FROM data_structures WHERE structureid = ".$structureid." LIMIT 1";

                                $structuredata = mysqli_fetch_assoc(mysqli_query($conn, $sql));

                                if (!$structuredata) {
                                        printf("Errormessage: %s\n", mysqli_error($conn));
                                }
                                $structurename = $structuredata['structurename'];
                        }
			//Vuln Date
			if(strpos($line, "timeLeft") === 0) {
				$timeleft = explode(" ",$line)[1];
				$timeleft = number_format($timeleft/10000000,0);
			}
			if(strpos($line, "timestamp") === 0) {
				$timestamp = explode(" ",$line)[1];
				$timestamp = $timestamp/10000000 - 11644473600;
				$timestamp = gmdate("Y/m/d H:i:s", $timestamp);
                        }
		}
		$body = $structurename." has lost shields!\r\n";
		$body = $body."Vulnerability will begin at ".date("Y/m/d H:i:s",strtotime('+'.$timeleft.' seconds',strtotime($timestamp)))." EVE time!";
	}

	//Mercenary Offered Assistance
        if($type == "MercOfferedNegotiationMsg") {
                $lines = explode(PHP_EOL, $text);
                foreach($lines as $line) {
			//Aggressor
			if(strpos($line, "aggressorID") === 0) {
				$aggressorid = explode(" ",$line)[1];

				if(substr($aggressorid,0,2)=="98"){
                                        // Lets get the corp data with a GET
                                        $remote_url = "https://esi.evetech.net/v4/corporations/$aggressorid/";

                                        $opts = array(
                                          'http' => array(
                                            'method' => 'GET',
                                            'header' => array(
                                                 "User-Agent: ...",
                                                 "Host: esi.evetech.net"
                                            ),
                                          )
                                        );

                                        $context = stream_context_create($opts);

                                        // Parse the results into an array
                                        $result = file_get_contents($remote_url, false, $context);
                                        $aggressordata = json_decode($result, true);
                                        $aggressorname = $aggressordata['name'];
                                }
                                if(substr($aggressorid,0,2)=="99"){
                                        // Lets get the alliance data with a GET
                                        $remote_url = "https://esi.evetech.net/v3/alliances/$aggressorid/";

                                        $opts = array(
                                          'http' => array(
                                            'method' => 'GET',
                                            'header' => array(
                                                 "User-Agent: ...",
                                                 "Host: esi.evetech.net"
                                            ),
                                          )
                                        );

                                        $context = stream_context_create($opts);

                                        // Parse the results into an array
                                        $result = file_get_contents($remote_url, false, $context);
                                        $aggressordata = json_decode($result, true);
                                        $aggressorname = $aggressordata['name'];
                                }
			}
			//Isk Value
			if(strpos($line, "iskValue") === 0) {
				$iskvalue = number_format(explode(" ",$line)[1],0);
			}
			//Mercenary ID
			if(strpos($line, "mercID") === 0) {
				$mercenaryid = explode(" ",$line)[1];

				if(substr($mercenaryid,0,2)=="98"){
                                        // Lets get the corp data with a GET
                                        $remote_url = "https://esi.evetech.net/v4/corporations/$mercenaryid/";

                                        $opts = array(
                                          'http' => array(
                                            'method' => 'GET',
                                            'header' => array(
                                                 "User-Agent: ...",
                                                 "Host: esi.evetech.net"
                                            ),
                                          )
                                        );

                                        $context = stream_context_create($opts);

                                        // Parse the results into an array
                                        $result = file_get_contents($remote_url, false, $context);
                                        $mercdata = json_decode($result, true);
                                        $mercname = $mercdata['name'];
                                }
				if(substr($mercenaryid,0,2)=="99"){
                                        // Lets get the alliance data with a GET
                                        $remote_url = "https://esi.evetech.net/v3/alliances/$mercenaryid/";

                                        $opts = array(
                                          'http' => array(
                                            'method' => 'GET',
                                            'header' => array(
                                                 "User-Agent: ...",
                                                 "Host: esi.evetech.net"
                                            ),
                                          )
                                        );

                                        $context = stream_context_create($opts);

                                        // Parse the results into an array
                                        $result = file_get_contents($remote_url, false, $context);
                                        $mercdata = json_decode($result, true);
                                        $mercname = $mercdata['name'];
                                }
			}
		}
		//Make the Body
		$body = $mercname." has offered assistance in our war with ".$aggressorname." for a price of ".$iskvalue." ISK!\r\n";
                if(substr($mercenaryid,0,2)=="98") {
                        $body = $body. "https://zkillboard.com/corporation/".$mercenaryid;
                }
                if(substr($mercenaryid,0,2)=="99") {
                        $body = $body."https://zkillboard.com/alliance/".$mercenaryid;
                }
	}

	//Structure Low Fuel
        if($type == "StructureFuelAlert") {
                $lines = explode(PHP_EOL, $text);
                foreach($lines as $line) {
                        //Structure Name
                        if(strpos($line, "structureID") === 0) {
                                $structureid = explode(" ",$line)[2];

                                //Get Structure Name
                                $conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
                                if (!$conn) {
                                        printf("Failed to connect to MySQL: " . mysqli_connect_error());
                                }

                                $sql = "SELECT structurename FROM data_structures WHERE structureid = ".$structureid." LIMIT 1";

                                $structuredata = mysqli_fetch_assoc(mysqli_query($conn, $sql));

                                if (!$structuredata) {
                                        printf("Errormessage: %s\n", mysqli_error($conn));
                                }
                                $structurename = $structuredata['structurename'];
                        }
                }
                $body = $structurename." is low on fuel!";
        }

	//War is Ending
	if($type == "CorpWarInvalidatedMsg" || $type == "CorpWarRetractedMsg" || $type == "CorpWarSurrenderMsg") {
                $lines = explode(PHP_EOL, $text);
                foreach($lines as $line) {
			//Declared By
			if(strpos($line, "declaredByID") === 0) {
                                $declaredbyid = explode(" ",$line)[1];

                                if(substr($declaredbyid,0,2)=="98"){
                                        // Lets get the corp data with a GET
                                        $remote_url = "https://esi.evetech.net/v4/corporations/$declaredbyid/";

                                        $opts = array(
                                          'http' => array(
                                            'method' => 'GET',
                                            'header' => array(
                                                 "User-Agent: ...",
                                                 "Host: esi.evetech.net"
                                            ),
                                          )
                                        );

                                        $context = stream_context_create($opts);

                                        // Parse the results into an array
                                        $result = file_get_contents($remote_url, false, $context);
                                        $declaredbydata = json_decode($result, true);
                                        $declaredbyname = $declaredbydata['name'];
                                }
				if(substr($declaredbyid,0,2)=="99"){
                                        // Lets get the alliance data with a GET
                                        $remote_url = "https://esi.evetech.net/v3/alliances/$declaredbyid/";

                                        $opts = array(
                                          'http' => array(
                                            'method' => 'GET',
                                            'header' => array(
                                                 "User-Agent: ...",
                                                 "Host: esi.evetech.net"
                                            ),
                                          )
                                        );

                                        $context = stream_context_create($opts);

                                        // Parse the results into an array
                                        $result = file_get_contents($remote_url, false, $context);
                                        $declaredbydata = json_decode($result, true);
                                        $declaredbyname = $declaredbydata['name'];
                                }
			}
		}
		$body = "Our war against ".$declaredbyname." is over! Hostilities will continue 24 hours from this message.";
	}

	//Format Discord Notif
	$message = "@here\r\n".$body;
	$data = ['content' => $message];
	$options = [
	    'http' => [
	        'method' => 'POST',
	        'header' => 'Content-Type: application/json',
	        'content' => json_encode($data)
	    ]
	];
	$context = stream_context_create($options);

	if($type == "WarDeclared" || $type == "StructureUnderAttack" || $type == "StructureLostShields" || $type == "MercOfferedNegotiationMsg" || $type == "StructureFuelAlert" || $type == "CorpWarInvalidatedMsg" || $type == "CorpWarRetractedMsg" || $type == "CorpWarSurrenderMsg") {

		//Check for recurring Structure Attack Messages for Same Structure
		if($type == "StructureUnderAttack") {
			if($laststructure <> $structurename) {
				//Send Normal Leadership Discord Notif
	                        $result = file_get_contents($leadershipwebhook, false, $context);
			}
			//Set laststructure
			$laststructure = $structurename;
		} else {
			//Send Normal Leadership Discord Notif
	                $result = file_get_contents($leadershipwebhook, false, $context);
		}
	}

	if($type == "MoonminingLaserFired" || $type == "MoonminingAutomaticFracture") {
	//Send Moons Discord Notif
		$result = file_get_contents($moonswebhook, false, $context);
	}

	//Send Text
	//mail($t1addresses . "," . $t2addresses,$subject,$text);

}

//Set notif flag to 1.
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) {
    printf("Failed to connect to MySQL: " . mysqli_connect_error());
}

$sql = "UPDATE data_notifs SET notified = 1";

$query = mysqli_query($conn, $sql);

if (!$query) {
    printf("Errormessage: %s\n", mysqli_error());
}

?>
