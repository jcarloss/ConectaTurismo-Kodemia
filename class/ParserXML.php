<?php 
/**
* ParserXML Class
* 
* @category 	Parser XML
* @author 		Juan Carlos Santana Arana
* @copyright 	Copyright (c) 2019
* @license 		http://opensource.org/licenses/gpl-3.0.html GNU Public License
* @link      	http://
* @version 		1.0
*/
	class ParserXML {
		/**
     	* URL del archivo a leer
     	*
     	* @var string
     	*/
		public $url;


		/**
     	* XML object to parser
     	*
     	* @var string
     	*/
		public $xml;

		/**
     	* Construct function
	    *
	    * @param string $url
	    *	   
	    * @return void
	    */

		public function __construct($url) {
			$this->url = $url;
			$this->xml = simplexml_load_file($url);
		}


		/**
     	* Return xml Object
	    *	    	     
	    * @return object
	    */
		public function loadXML(){
			return $this->xml;
		}		

		/**
     	* Return object's childs
	    * @param string $route	    	    	  
	    * @param object $object
	    * @return array[object]
	    */
		public function getChildsObjectWithPath($route,$object){
			return $object->xpath($route);
		}

		/**
     	* Return parent node
	    *
	    * @param object $object
	    *	   
	    * @return object
	    */
		public function getNodeParent($object){
			return $object->xpath("parent::*");
		}

		/**
     	* Return node attributes
	    *
	    * @param object $object
	    *	   
	    * @return object
	    */
		public function getAttributesNode($object){
			return $object->attributes();
		}

		/**
     	* Return date in format Y-m-d (response2.xml)
	    *
	    * @param string $date
	    *	   
	    * @return string
	    */
		public function formatDate($date){
			$date = str_replace('/', '-', $date);
			return date('Y-m-d', strtotime(substr($date,0,10)));
		}

		/**
     	* Return hours difference between 2 dates
	    *
	    * @param string $from
	    * @param string $to
	    *	   
	    * @return object
	    */

		public function hours($from, $to){   
	        $from = new DateTime(str_replace("/", "-", $from));
	        $to = new DateTime(str_replace("/", "-",$to));
	        $diff = $to->diff($from);                   
	        return $diff;
	    } 
	}
	
?>