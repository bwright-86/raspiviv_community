
<?php

	$db = mysql_connect("localhost","datalogger","datalogger") or die("DB Connect error");
	mysql_select_db("datalogger");

	$qt = "SELECT temperature FROM datalogger where sensor = 8 ORDER BY date_time DESC LIMIT 1";
	$dt = mysql_query($qt);
	$tempSensor=(float)mysql_fetch_object($dt)->temperature;

	$qh = "SELECT humidity FROM datalogger where sensor = 8 ORDER BY date_time DESC LIMIT 1";
	$dh = mysql_query($qh);
	$humiditySensor=(float)mysql_fetch_object($dh)->humidity;


	//change threshold depening on time of day
	$tempThreshold;
	$tempNight = 24.5;  	// 24.5
	$tempDay = 30.0;		// 26.5

	$humidityThreshold;
	$humidityMin = 70.0;
	$humidityNight = 85.0;
	$humidityDay = 95.0;

	$override = false;		// override temperature and rain every minute
	$pumpPrimer = false; 	// set this to true to build up rain system pressure
	$debugMode = true;

	$t = time();
	$curentTime = date('H:i');
	$morningTime = ('10:00');
	$eveningTime = ('22:00');
	$rainShedule = array('12:00', '18:00'); //TODO: extend with timer per time
	$rainTimeShedule = 5;
	$rainTime = 1; 			// time in seconds to rain
	$windTime = 10;			// time to vent in seconds



	//night time climate
	if (($curentTime < $morningTime) or ($curentTime > $eveningTime)) {
		$tempThreshold = $tempNight;
		$humidityThreshold = $humidityNight;

		//wind when humidity is high
		if ($humiditySensor > $humidityThreshold) {
			$windTime = (60 / (100-$humidityThreshold)*($humiditySensor-$humidityThreshold)); 	//TODO: does this time breakdown make sense during night?
			bringTheAir($windTime);
		}
		//what to do when temps are high?

	}

	//day time climate
	else {
		$humidityThreshold = $humidityDay;
		$tempThreshold = $tempDay;

		//trigger rain shedules
		if (in_array($curentTime, $rainShedule)) {
			letItRain($rainTimeShedule);
		}


		//react to high temperatures
		if ($tempSensor > $tempThreshold or $override==true) {
			//adjust rain time depending how high the temp is above our limit
			$tempDelta = ($tempSensor - $tempThreshold);
			if (($tempDelta > 0) and ($tempDelta < 10)) {
				$tempDelta = $tempDelta + $rainTime;
				letItRain($tempDelta);
				bringTheAir($windTime);
			} else {
				letItRain($rainTime);
				bringTheAir($windTime);
			}
		}


		//wind depending on how much humidity is over our limit
		if ($humiditySensor > $humidityThreshold) {
			$windTime = (60 / (100-$humidityThreshold)*($humiditySensor-$humidityThreshold));
			bringTheAir($windTime);
		}


		//react to low humidity
		if ($humiditySensor < $humidityMin) {
			$humidityDelta = ($humidityMin - $humiditySensor);
			if (($humidityDelta > 0) and ($humidityDelta < 10)) {
				$humidityDelta = $rainTime;
				letItRain($humidityDelta);
			} else {
				letItRain($rainTime);
			}
		}



		//override to pressure pump
		if ($pumpPrimer==true and $override==true) {
			$i = 0;
			while($i < 30) {
				letItRain($delta);
				$i++;
			}
		}
	}



	// functions
	function letItRain($delta) {
		exec('/usr/local/bin/gpio mode 2 out');
		exec('/usr/local/bin/gpio write 2 0');
		sleep($delta);
		exec('/usr/local/bin/gpio write 2 1');
	}


	function bringTheAir($delta) {
		exec('/usr/local/bin/gpio mode 5 out');
		exec('/usr/local/bin/gpio write 5 1');
		//time till wind stops
		sleep ($delta);
		exec('/usr/local/bin/gpio write 5 0');
	}


	mysql_query($qt);
	mysql_query($qh);
	mysql_close($db);

?>
