<?php 
	include("class/ParserXML.php");
	$parserXML = new ParserXML("response.xml");
	$response = array();

	// Cargamos el xml y convertimos en objeto
	$xml = simplexml_load_file("xml/response.xml");	
	// Extraemos la lista de vuelos
	$FlightDetailsList 	= $parserXML->getChildsObjectWithPath('//air:FlightDetails',$xml);
	
	$response["count"] 	= sizeof($FlightDetailsList);
	// Parseamos la lista de vuelos @FlightDetailsList
	foreach($FlightDetailsList as $FlightDetails) { 
			//Buscamos los vuelos disponibles de las distintas aerolineas <air:FlightDetailsRef Key="@Key"/> donde @Key es el atributo obtenido de @FlightDetails->Key		
			$details = $parserXML->getAttributesNode($FlightDetails);
			$temp = array(
					"journey"	=>	(string)$details->Key,
					"airlines"	=>	array(
						"code"	=>	"No existe a nivel FlightDetails"//¿De donde lo obtengo?
					),
					"departure"	=>	array(
						"airport"	=>	array(
							"code"		=>	(string)$details->Origin,
							"terminal"	=>	(string)$details->OriginTerminal
						),
						"date"	=>	date("Y-m-d",strtotime((string)$details->DepartureTime)),
						"time"	=>	date("H:i:s",strtotime((string)$details->DepartureTime))
					),
					"arrival"	=>	array(
						"airport"	=>	array(
							"code"	=>	(string)$details->Destination,
							"terminal"	=>	(string)$details->DestinationTerminal
						),
						"date"	=>	date("Y-m-d",strtotime((string)$details->ArrivalTime)),
						"time"	=>	date("H:i:s",strtotime((string)$details->ArrivalTime))
					),
					"duration"	=>	array(
						"hours"		=>	round(((string)$details->FlightTime)/60),
						"minutes"	=>	round(((string)$details->FlightTime)%60)
					)
				);
			$scale	= 0;
			// Extaremos los detalles de referencia para conocer los segmentos padres
//			$FlightDetailsRefs = $xml->xpath('//air:FlightDetailsRef[@Key="'.(string)$details->Key.'"]');
			
			$FlightDetailsRefs = $parserXML->getChildsObjectWithPath('//air:FlightDetailsRef[@Key="'.(string)$details->Key.'"]',$xml);

			foreach ($FlightDetailsRefs as $indice => $FlightDetailsRef) {
				// Obtenemos el nodo padre inmediato <air:AirSegment /> de cada aerolinea 				
				$AirSegment = $parserXML->getNodeParent($FlightDetailsRef);

				// Atributos $AirSegement
				$AirSegmentDetails = $parserXML->getAttributesNode($AirSegment[0]);

				$CodeshareInfo = $parserXML->getChildsObjectWithPath("air:CodeshareInfo",$AirSegment[0]);				
				//Seteamos 'Flight' por default
				$typeFlight  = "flight";
				if(sizeof($CodeshareInfo) && sizeof($FlightDetailsRefs) > 1){				
					$typeFlight  = "scale";
					//Incrementamos las escalas
					$scale++;
				}
				// Extraemos los parametros atributos del objeto class {code,type} a partir del nodo <air:AirSegmentRef />
				$AirSegmentRef = $parserXML->getChildsObjectWithPath('//air:AirSegmentRef[@Key="'.(string)$AirSegmentDetails->Key.'"]',$xml);

				// Nodo padre inmediato de AirSegment
				$journey = $parserXML->getNodeParent($AirSegmentRef[0]);

				//Nodo padre inmediato de Journey 
				$AirPricingSolution = $parserXML->getNodeParent($journey[0]);
				$AirPricingSolutionDetails = $parserXML->getAttributesNode($AirPricingSolution[0]);

				// Extraemos datos de AirPricingInfo
				$AirPricingInfo = $parserXML->getChildsObjectWithPath('air:AirPricingInfo',$AirPricingSolution[0]);		

				//En el nodo <air:BookingInfo/> extraemos los atributos de 'class'
				$BookingInfo = $parserXML->getChildsObjectWithPath('air:BookingInfo',$AirPricingInfo[0]);
				
				$BookingInfoDetails = $parserXML->getAttributesNode($BookingInfo[0]);

				
				$temp["segments"][] = array(
					"type"	=> $typeFlight,// Interpreto que al existir el atributo 'operatingAirline' es un escala
					"changeTerminal"	=>	(string)$AirSegmentDetails->ChangeOfPlane." (Suponiendo que es el atributo 'ChangeOfPlane' )",
					"departure"	=>	array(
						"airport"	=>	array(
							"code"		=>	(string)$AirSegmentDetails->Origin,
							"terminal"	=>	"No existe a nivel AirSegment"
						),
						"date"	=>	date("Y-m-d",strtotime((string)$AirSegmentDetails->DepartureTime)),
						"time"	=>	date("H:i:s",strtotime((string)$AirSegmentDetails->DepartureTime))
					),
					"arrival"	=>	array(
						"airport"	=>	array(
							"code"		=>	(string)$AirSegmentDetails->Destination,
							"terminal"	=>	"No existe a nivel AirSegment"
						),
						"date"	=>	date("Y-m-d",strtotime((string)$AirSegmentDetails->ArrivalTime)),
						"time"	=>	date("H:i:s",strtotime((string)$AirSegmentDetails->ArrivalTime))
					),
					"isNightly"	=> false,
					"duration"	=>	array(
						"hours"		=>	round(((string)$AirSegmentDetails->FlightTime)/60),
						"minutes"	=>	round(((string)$AirSegmentDetails->FlightTime)%60)
					),
					"flightNumber"	=>	(string)$AirSegmentDetails->FlightNumber,
					"airCraft"		=>	"No existe",
					"airline"	=>	array(
						"code"	=>	(string)$AirSegmentDetails->Carrier,
					),
					"operatingAirline"	=>	array(
						"code"	=> sizeof($CodeshareInfo)?(string)$CodeshareInfo[0]->attributes()->OperatingCarrier:""
					),
					"class"	=>array(
						"code"	=>	(string)$BookingInfoDetails->BookingCode,
						"type"	=>	(string)$BookingInfoDetails->CabinClass,
					)
					/** Importante!, preguntar a cliente ¿Por que los segmentos tienen varios bookings? **/
				);
			}		
			$temp["scale"]	= $scale;
			$currency = substr((string)$AirPricingSolutionDetails->TotalPrice,0,3);
				$temp["option"]	= array(
					"price"	=>	array(
						"amount"	=>	str_replace($currency,"",(string)$AirPricingSolutionDetails->TotalPrice),
						"currency"	=>	$currency,
					)
				);	
			$response["flights"][]["journeys"][] = $temp;
		}
?>
<?php
	//Formateamos la salida
	header('Content-Type: application/json; charset=utf-8');
	echo json_encode($response,JSON_PRETTY_PRINT)
?>
	