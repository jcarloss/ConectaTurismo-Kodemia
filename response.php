
<?php 
	/** Este script carga el contenido del archivo response2.xml y con una serie procesos genera como salida texto en formato JSON **/
 	include("class/ParserXML.php");
	
	$response = array();
	$parserXML = new ParserXML("response2.xml");
	// Cargamos el xml y convertimos en objeto	
	
	$xml = simplexml_load_file("xml/response2.xml");	
	$ns = $xml->getNamespaces(true);	
	$soap = $xml->children($ns['s']);
	$body = $parserXML->getChildsObjectWithPath("//s:Body",$soap);

	// Obtenemos todos los itinerarios
	$AirAvailSearchResponse = $body[0]->AirAvailSearchResponse;
	$AirItineraries = $AirAvailSearchResponse->AirAvailSearchResult->AirAvail->AirItineraries;	

	// Guardamos el total de itinerarios
	$response["count"] = sizeof($AirItineraries[0]->AirItinerary);

	// Precios de los itinerarios para incluirlos posteriormente 
	$AirPricingGroupOption = $AirAvailSearchResponse->AirAvailSearchResult->AirAvail->AirPricingGroups->AirPricingGroup->AirPricingGroupOptions->AirPricingGroupOption;
	
	foreach($AirItineraries[0]->AirItinerary as $AirItinerarie){

		//Duracion del viaje total sin contar segmentos
		$duration = $parserXML->hours((string)$AirItinerarie->DepartureDateTime,(string)$AirItinerarie->ArrivalDateTime);
		
		$temp = array(
			"journey"	=>	(string)$AirItinerarie->ItineraryID,
			"airlines"	=> array(
				"code"	=> 'No existe a nivel de AirItinerary'
			),
			"departure"	=>	array(
				"airport"	=>	array(
					"code"	=>	trim((string)$AirItinerarie->DepartureAirportLocationCode),					
				),
				"date"	=>	$parserXML->formatDate((string)$AirItinerarie->DepartureDateTime),
				"time"	=>	substr($AirItinerarie->DepartureDateTime,11,5)
			),
			"arrival"	=>	array(
				"airport"	=>	array(
					"code"	=>	trim((string)$AirItinerarie->ArrivalAirportLocationCode),					
				),
				"date"	=>	$parserXML->formatDate($AirItinerarie->ArrivalDateTime),
				"time"	=>	substr($AirItinerarie->ArrivalDateTime,11,5)
			),
			"duration"	=>	array(
				"hours"	=>	$duration->h,
				"minutes"	=> $duration->i
			)
		);

		// Escalas de vuelo
		$AirItineraryLegs = $AirItinerarie->AirItineraryLegs->AirItineraryLeg;

		//Precio del itinerario de acuerdo al id partiendo del nodo padre
		
		$AirPricedItineraryLeg = $parserXML->getChildsObjectWithPath('//ItineraryID[.="'.$AirItinerarie->ItineraryID.'"]/parent::*',$AirAvailSearchResponse);//
		// Buscamos el nodo padre 'AirPricedItinerary'		
		foreach ($AirPricedItineraryLeg as $key => $parentNode) {
		
			// Buscamos el nodo padre AirPricedItinerary
			if($parentNode->getname() == "AirPricedItinerary"){
				$class = $parentNode->AirPricedItineraryLegs;
				break;
			}
		}		

		$countSegment = 0;
		$scale = 0;
		$existScale  = false;

		// Leemos cada segmento dle itinerario
		foreach ($AirItineraryLegs as $key => $AirItineraryLeg) {

			$typeFlight = "flight";

			$changeTerminal = false;
			if($existScale){
				// En caso de que el itinerario contenga mas 1 viaje comparamos la terminal de la ultima llegada con la terminal de la nueva salida, si son distintas entonces hubo un cambio de terminal
				if($lastArrival != (string)$AirItineraryLeg->DepartureAirportTerminal)
					$changeTerminal = true;
				
			}

			// Si el destino es distinto del destino principal entonces es una escala
			if((string)$AirItineraryLeg->ArrivalAirportLocationCode != (string)$AirItinerarie->ArrivalAirportLocationCode){
				//Incrementamos las escalas
				$typeFlight = "scale";
				$scale++;
				$existScale = true;
				$lastArrival = (string)$AirItineraryLeg->ArrivalAirportTerminal;
			}

			// Obtenemos formato de fecha
			$date = $parserXML->formatDate($AirItineraryLeg->ArrivalDateTime);

			//Duration de segmento o escala
			$duration = $parserXML->hours($AirItineraryLeg->DepartureDateTime,$AirItineraryLeg->ArrivalDateTime);

			// Segmentos de vuelo
			$temp["segments"][] = array(
				"type"	=>	$typeFlight,
				"changeTerminal"	=> 	$changeTerminal,
				"departure"	=> array(
					"airport"	=> array(
						"code"	=>	trim((string)$AirItineraryLeg->DepartureAirportLocationCode),
						"terminal"	=>	(string)$AirItineraryLeg->DepartureAirportTerminal
					),
					"date"	=>	$parserXML->formatDate($AirItineraryLeg->DepartureDateTime),
					"time"	=>	substr($AirItineraryLeg->DepartureDateTime,11,5)
				),
				"arrival"	=> array(
					"airport"	=> array(
						"code"	=>	trim((string)$AirItineraryLeg->ArrivalAirportLocationCode),
						"terminal"	=>	(string)$AirItineraryLeg->ArrivalAirportTerminal
					),
					"date"	=>	$date,
					"time"	=>	substr($AirItineraryLeg->ArrivalDateTime,11,5)
				),
				// Validamos si el vuelo es entre las 18:00 y las 6 hrs para identificar que es vuelo nocturno
				"isNightly"	=>	(substr($AirItineraryLeg->ArrivalDateTime,11,5) > "18:00" ||  substr($AirItineraryLeg->ArrivalDateTime,11,5) < "06:00")?"true":"false",
				"duration"	=>	array(
					"hours"	=>	$duration->h,
					"minutes"	=> $duration->i
				),
				"flightNumber"	=> (string)$AirItineraryLeg->FlightNumber, 
              	"aircraft"		=> (string)$AirItineraryLeg->AircraftType, 
              	"airline"	=>	array(
                	"code"	=> (string)$AirItineraryLeg->OperatingCarrierCode
              	), 
              	"operatingAirline"	=>	array(
                	"code"	=>	(string)$AirItineraryLeg->OperatingCarrierCode
              	), 
              	//Preguntar ¿Qué sucede con los itinerarios que poseen mas de un grupo?
				"class"	=> array(
					"code"	=>	(string)$class->AirPricedItineraryLeg[$countSegment]->CabinClass,
					"type"	=>	(string)$class->AirPricedItineraryLeg[$countSegment]->CabinType,
				)
				
			);

			$countSegment++;	
		}

		$temp["scale"]	= $scale;

		// Accedemos al padre 'AirPricingGroup' que contiene los costos como hijos inmediatos
		$AirPricingGroup = $class->xpath("parent::AirPricedItinerary")[0]->xpath("parent::AirPricedItineraries")[0]->xpath("parent::AirPricingGroupOption")[0]->xpath("parent::AirPricingGroupOptions")[0]->xpath("parent::AirPricingGroup")[0];

		$amount = $AirPricingGroup->AdultTicketAmount + $AirPricingGroup->ChildrenTicketAmount + $AirPricingGroup->InfantTicketAmount + $AirPricingGroup->AdultTaxAmount + $AirPricingGroup->ChildrenTaxAmount + $AirPricingGroup->InfantTaxAmount + $AirPricingGroup->AgencyFeeAmount + $AirPricingGroup->AramixFeeAmount - $AirPricingGroup->DiscountAmount;

		$temp["option"] = array(
			"price"		=>	array(
				"amount" =>	round($amount,2),
				"currency"	=> "USD"
			)
		);
		$response["journeys"][]=$temp;
	}
?>
<?php
	//Formateamos la salida
	header('Content-Type: application/json; charset=utf-8');
	echo json_encode($response,JSON_PRETTY_PRINT)
?>	