<?php

// JSON ENCODE AS ARRAY! PLEASE - FOR THE LOVE OF ALL THAT IS HOLY

include_once('vendor/autoload.php');
include_once('vendor/rbsock/src/rb.php');

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;


$x = R::setup('sqlite:local.sqlite','test','test');

class Super_Model extends RedBean_SimpleModel
{
	public function open() {
	}

	public function dispense() {
	}

	public function update() {
	}

	public function after_update() {
		global $lifeCycle;
		global $chat;
		print_r($chat->subscribers);
		print_r($chat->subscriptions);
		$lifeCycle .= "Called AFTER update.";
	}

	public function delete() {
	}

	public function after_delete() {
	}
}



// Dynamically create these suckers?
class Model_Books extends Super_Model { }

class Chat implements MessageComponentInterface {

	var $valid_commands = array('SET', 'GET', 'DEL', 'POP', 'SUB');

	public static $beanlist = array();
	public static $beancnt	= 0;

	public $subscribers;
	public $subscriptions;
	protected $clients;
	protected $notify;

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
		$conn->send(json_encode(array(array('RBWS'=>R::getVersion())), TRUE));
	}

    public function pushNotify(ConnectionInterface $from, $msg) {
        $numRecv = count($this->clients) - 1;
        echo sprintf('Connection %d sending message "%s" to %d other connection%s' . "\n"
            , $from->resourceId, $msg, $numRecv, $numRecv == 1 ? '' : 's');

        foreach ($this->clients as $client) {
            if ($from !== $client) {
                // The sender is not the receiver, send to each client connected
                $client->send($msg);
            }
        }
    }

	public static function parseFormJson($js)
	{
		global $lifeCycle;

		print "GOT HERE: {$lifeCycle}\n";
		$lifeCycle = '';

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

	/* Updates all the connected clients who are current subscribers to this specific bean/id */
	function updateSubscribersTo($BEAN, $id, $happened = 'SET')
	{
		if ($happened == 'SET') $thisbean = R::load($BEAN, $id);

		print "UPDATING ".count($this->subscribers[$BEAN][$id]). " ({$happened}) SUBSCRIBERS TO {$BEAN}->{$id}\n";

		print_r($this->subscribers[$BEAN]);

		if (!isset($this->subscribers[$BEAN])) return(false);
		foreach($this->subscribers[$BEAN][$id] as $subscriber=>$status)
		{
			// Possibly more statii to come
			if ($status == 'SUB') 
			{
				foreach($this->clients as $c)
				{
					if ($c->resourceId == $subscriber)
					{
						print "SENDING ".$c->resourceId. " WHAT {$happened} TO {$BEAN} #{$id}\n";
						switch($happened) 
						{
							case 'DEL':
								unset($this->subscribers[$BEAN][$id]);
								$c->send("BUS DEL {$BEAN} {$id}");
								break;
							case 'SET': 
								// Auto push updates to beans/records
								$c->send(json_encode(array('OK'=>array($thisbean->export())), TRUE));
								break;
							default:
								break;
						}
					}
				}
			}
		}
	}

	public function onMessage(ConnectionInterface $from, $msg)
	{
		print_r("CONN {$from->resourceId} :: ");
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
						$thisbean->id = $id;
						$from->send(json_encode(array('OK'=>array($thisbean->export())), TRUE));

						$this->updateSubscribersTo($BEAN, $id, 'SET');

						break;

					case 'GET':
						list($payload_bind, $payload_values) = self::buildBindings($payload_data);
						$tmpbean = R::findAll($BEAN, $payload_bind, $payload_values);
						if ($tmpbean)
						{
							$from->send(json_encode(array('OK'=>R::exportAll($tmpbean, TRUE))));
						}
						else $from->send(json_encode(array('ERR'=>$BEAN. ' not found.')));
						break;

					case 'SUB':
						$payload_bind	= '';
						list($payload_bind, $payload_values) = self::buildBindings($payload_data);
					
						$tmpbean = R::findOne($BEAN, $payload_bind, $payload_values);
						if ($tmpbean) 
						{
							$from->send(json_encode(array('OK'=>array($tmpbean->export())), TRUE));
						}
						else $from->send(json_encode(array('ERR'=>$BEAN. ' not found.')));

						// Watch for memory compounding
						$this->subscribers[$BEAN][$tmpbean->id][$from->resourceId] = 'SUB';
						$this->subscriptions[$from->resourceId] = array($BEAN=>$tmpbean->id);

						print "This guy subscribed to {$BEAN}->{$tmpbean->id} .. # BEANS subscribed to: " . count($this->subscribers[$BEAN]);

						break;

					// POP and DEL are exactly the same thing at the moment
					case 'POP':
						$payload_bind	= '';
						list($payload_bind, $payload_values) = self::buildBindings($payload_data);
					
						$tmpbean = R::findOne($BEAN, $payload_bind, $payload_values);
						if ($tmpbean) 
						{
							$from->send(json_encode(array('OK'=>array($tmpbean->export())), TRUE));
							R::trash($tmpbean);
							$this->updateSubscribersTo($BEAN, $tmpbean->id, 'DEL');
						}
						else $from->send(json_encode(array('ERR'=>$BEAN. ' not found.')));
						break;

					case 'DEL':
						$payload_bind	= '';
						list($payload_bind, $payload_values) = self::buildBindings($payload_data);
					
						$tmpbean = R::findOne($BEAN, $payload_bind, $payload_values);
						if ($tmpbean) 
						{
							$from->send(json_encode(array('OK'=>array($tmpbean->export())), TRUE));
							R::trash($tmpbean);
							$this->updateSubscribersTo($BEAN, $tmpbean->id, 'DEL');
						}
						else $from->send(json_encode(array('ERR'=>$BEAN. ' not found.')));
						break;
					default : 
						$from->send(json_encode(array('ERR'=>$BEAN. ' -- not found.')));
				}
			}
		}
		else $from->send(json_encode(array('ERR'=>'Invalid command, SOZ')));
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
		$conn->send(json_encode(array('ERR'=>$e->getMessage())));
		$conn->close();
	}
}

$chat = new Chat();
$server = IoServer::factory(
	new HttpServer(
		new WsServer(
			$chat
		)
	), 8080
);

$server->run();

