<?php
// JSON ENCODE AS ARRAY! PLEASE - FOR THE LOVE OF ALL THAT IS HOLY

include_once('vendor/autoload.php');

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

if (file_exists('../weird_database.php')) include_once('../weird_database.php');
else R::setup('sqlite:local.sqlite','test','test');


// Dynamically // Factory create these suckers?
//class Model_Books extends Super_Model { }

class Chat implements MessageComponentInterface {

	var $valid_commands = array('SET', 'GET', 'DEL', 'POP', 'SUB');

	public static $beanlist = array();
	public static $beancnt	= 0;

	public $subscribers;
	public $subscriptions;

	protected $clients;
	protected $notify;

	static public function csend(ConnectionInterface $conn, $data) { 
		print "\n".print_r($data,true)."\n".date('Y-m-d H:i:s')."------------------\n";
		$conn->send($data);
	}
	
	public function getClients()
	{
		return($clients);
	}

	public function __construct() {
		$this->clients = new \SplObjectStorage;
	}

	public function onOpen(ConnectionInterface $conn) {
		print "Connection open.\n";
		$this->clients->attach($conn);
		//$conn->send(json_encode(array(array('RBWS'=>R::getVersion())), TRUE));
		self::csend($conn, json_encode(array(array('RBWS'=>R::getVersion())), TRUE));
	}

    public function pushNotify(ConnectionInterface $from, $msg) {
        $numRecv = count($this->clients) - 1;
        echo sprintf('Connection %d sending message "%s" to %d other connection%s' . "\n"
            , $from->resourceId, $msg, $numRecv, $numRecv == 1 ? '' : 's');

        foreach ($this->clients as $client) {
			// The sender is not the receiver, send to each client connected
            if ($from !== $client) {
                self::csend($client,$msg);
            }
        }
    }

	public static function parseFormJson($js)
	{
		$final = array();
		$formdata = json_decode($js,true);
		foreach($formdata as $fields)
		{
			$final[$fields['name']] = $fields['value'];
		}
	    return($final);
	}

	public static function buildBindings($payload_data)
	{
		$plb			= 0; // Bindig count
		$payload_bind	= ''; // The string to create for bindings
		$payload_values = array();

		if (isset($payload_data))
		{
			foreach($payload_data as $k=>$v)
			{
				if ($plb++ > 0) $payload_bind .= " AND";
				if (!empty($v))
				{
					$payload_bind .= " `{$k}`=?";
					$payload_values[] = $v;
				}
			}
		}
		return (array($payload_bind, $payload_values));
	}

	public function onMessage(ConnectionInterface $from, $msg)
	{
		print("CONN {$from->resourceId} :: ");
		list($CMD, $BEAN, $PAYLOAD) = explode(' ',$msg,3);

		if (isset($CMD) && isset($BEAN))
		{
			$CMD	= strtoupper($CMD);
			$BEAN	= preg_replace('/[^a-z]/','',strtolower($BEAN));

			// If payload is JSON
			if (isset($PAYLOAD))
			{
				// If payload is simple JSON format
				if($PAYLOAD[0] == '{') $payload_data = json_decode($PAYLOAD, true);

				// If payload is FORM submitted json serialized form data
				elseif($PAYLOAD[0] == '[') 
				{ 	// Probably came from json form post
					$payload_data = self::parseFormJson($PAYLOAD);
				}
			}
			else $payload_data = $PAYLOAD;

			print "Received {$CMD} for {$BEAN} with payload {$PAYLOAD}\n";
			if (in_array($CMD, $this->valid_commands)) 
			{

				$payload_bind	= '';
				switch($CMD) 
				{
					case 'SET':
						$thisbean = R::dispense($BEAN);
						if (is_array($payload_data)) $thisbean->import($payload_data);
						$id = R::store($thisbean);

						// Auto push updates to beans/records
						if ($id) { 
							$thisbean->id = $id;

							$msg = json_encode(array('OK'=>array($thisbean->export())));
							self::pushNotify($from, $msg); 
							//$from->send(json_encode(array('OK'=>array($thisbean->export())), TRUE));
							// self::csend($from,json_encode(array('OK'=>array($thisbean->export())), TRUE));
						}
						else {
							//$from->send(json_encode(array('ERR'=>array('message'=>'no payload data')), TRUE));
							self::csend($from,json_encode(array('ERR'=>array('message'=>'no payload data')), TRUE));
						}

						break;

					case 'GET':
						list($payload_bind, $payload_values) = self::buildBindings($payload_data);
						$tmpbean = R::findAll($BEAN, $payload_bind, $payload_values);
					 	$musical_fruit = array();
						foreach($tmpbean as $bean)
						{
							$musical_fruit[] = $bean->export();
						}	
						if (count($musical_fruit)) {
							//$from->send(json_encode(array('OK'=>$musical_fruit)));
							self::csend($from,json_encode(array('OK'=>$musical_fruit)));
						}
						else {
							//$from->send(json_encode(array('ERR'=>$BEAN. ' not found.')));
							self::csend($from,json_encode(array('ERR'=>$BEAN. ' not found.')));
						}

						/*
						if ($tmpbean)
						{
							$from->send(json_encode(array('OK'=>R::exportAll($tmpbean, TRUE))));
						}
						else $from->send(json_encode(array('ERR'=>$BEAN. ' not found.')));
						*/
						break;

					case 'SUB':
						list($payload_bind, $payload_values) = self::buildBindings($payload_data);
					
						$tmpbean = R::findOne($BEAN, $payload_bind, $payload_values);
						if ($tmpbean) 
						{
							//$from->send(json_encode(array('OK'=>array($tmpbean->export())), TRUE));
							self::csend($from,json_encode(array('OK'=>array($tmpbean->export())), TRUE));
						}
						else {
							//$from->send(json_encode(array('ERR'=>$BEAN. ' not found.')));
							self::csend($from,json_encode(array('ERR'=>$BEAN. ' not found.')));
						}

						// Watch for memory compounding
						$this->subscribers[$BEAN][$tmpbean->id][$from->resourceId] = 'SUB';
						$this->subscriptions[$from->resourceId] = array($BEAN=>$tmpbean->id);

						print "This guy subscribed to {$BEAN}->{$tmpbean->id} .. # BEANS subscribed to: " . count($this->subscribers[$BEAN]);

						break;

					// POP and DEL are exactly the same thing at the moment
					case 'POP':
					case 'DEL':
						list($payload_bind, $payload_values) = self::buildBindings($payload_data);
					
						$tmpbean = R::findOne($BEAN, $payload_bind, $payload_values);
						if ($tmpbean) 
						{
							//$from->send(json_encode(array('OK'=>array($tmpbean->export())), TRUE));
							$msg = json_encode(array('DEL'=>array('BEAN'=>$BEAN,'id'=>$tmpbean->id)),TRUE);
							self::pushNotify($from, $msg); 
							// R::trash($tmpbean);
						}
						else {
							//$from->send(json_encode(array('ERR'=>$BEAN. ' not found.')));
							self::csend($from,json_encode(array('ERR'=>$BEAN. ' not found.'),true));
						}
						break;
					default : 
						//$from->send(json_encode(array('ERR'=>$BEAN. ' -- not found.')));
						self::csend($from,json_encode(array('ERR'=>$BEAN. ' -- not found.'),true));
				}
			}
		}
		else {
			//$from->send(json_encode(array('ERR'=>'Invalid command, SOZ')));
			self::csend($from,json_encode(array('ERR'=>'Invalid command, SOZ')));
		}
	}

	public function onClose(ConnectionInterface $conn)
	{
		print_r("DISCONNECT: {$conn->resourceId}\n");

		// Check for subscriptions
		if (!empty($this->subscriptions[$conn->resourceId]))
		{
			print_r($this->subscribers);
			print_r($this->subscriptions);
			foreach($this->subscriptions[$conn->resourceId] as $BEAN=>$id)
			{
				unset($this->subscribers[$BEAN][$id][$conn->resourceId]);
			}
			// Remove all subscriptions
			unset($this->subscriptions[$conn->resourceId]);
			print_r($this->subscribers);
			print_r($this->subscriptions);
		}

		$this->clients->detach($conn);
		print "Connection closed.\n";
	}

	public function onError(ConnectionInterface $conn, \Exception $e)
	{
		print "* Cerebral hemorrhage: ".$e->getMessage()."\n";
		//$conn->send(json_encode(array('ERR'=>$e->getMessage())));
		self::csend($conn,json_encode(array('ERR'=>$e->getMessage())));
		$conn->close();
	}
}

$chat = new Chat();
$server = IoServer::factory(
	new HttpServer(
		new WsServer(
			$chat
		)
	), 8888
);

$server->run();

