<?php

include_once('vendor/autoload.php');
include_once('vendor/rbsock/src/rb.php');

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;


$x = R::setup('sqlite:local.sqlite','test','test');

class Chat implements MessageComponentInterface {

	var $valid_commands = array('SET', 'GET', 'DEL', 'POP');

	public static $beanlist = array();
	public static $beancnt	= 0;

	protected $clients;
	protected $notify;

	public function __construct() {
		$this->clients = new \SplObjectStorage;
	}

	public function onOpen(ConnectionInterface $conn) {
		print "Connection open.\n";
		$this->clients->attach($conn);
		$conn->send(json_encode(array('RBWS'=>R::getVersion())));
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
				$payload_bind .= " `{$k}`=?";
				$payload_values[] = $v;
			}
		}
		return (array($payload_bind, $payload_values));
	}

	public function onMessage(ConnectionInterface $from, $msg)
	{
		print_r($from->resourceId);
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

				// If you try to "set" a bean with an ID, it will attempt to 
				// "UPDATE" that partuclar bean.
				// If that bean does not exist, it will still return as if it were valid!
				// This is undesired behavior.
				if ($CMD == 'SET') 
				{
					$thisbean = R::dispense($BEAN);
					if (is_array($payload_data)) $thisbean->import($payload_data);
					$id = R::store($thisbean);
					$from->send(json_encode(array('id'=>$id)));
				}
				else if ($CMD == 'GET') 
				{
					$payload_bind	= '';
					list($payload_bind, $payload_values) = self::buildBindings($payload_data);
					$tmpbean = R::find($BEAN, $payload_bind, $payload_values);
					if ($tmpbean)
					{
						$from->send(json_encode(R::exportAll($tmpbean, TRUE)));
					}
					else $from->send(json_encode(array('ERR',$BEAN. ' not found.')));
				}
				// Subscribe to a particular bean for updates
				else if ($CMD == 'SUB')
				{

				}
				else if ($CMD == 'POP') 
				{
					$payload_bind	= '';
					list($payload_bind, $payload_values) = self::buildBindings($payload_data);
					$tmpbean = R::findOne($BEAN, $payload_bind, $payload_values);
					if ($tmpbean) 
					{
						$from->send(json_encode($tmpbean->export(), TRUE));
						R::trash($tmpbean);
					}
					else $from->send(json_encode(array('ERR',$BEAN. ' not found.')));
				}
				else if ($CMD == 'DEL')
				{
					$tmpbean = R::load($BEAN,$payload_data['id']);
					if ($tmpbean) 
					{ 
						$tmpval = $tmpbean->export();
						R::trash($tmpbean);
						$from->send(json_encode($tmpval));
					}
					else $from->send(json_encode(array('ERR',$BEAN. ' not found.')));
				}
			}
		}
		else $from->send(json_encode(array('ERR'=>'Invalid command, SOZ')));
	}

	public function onClose(ConnectionInterface $conn)
	{
		print_r("Disconnect: {$conn->resourceId}\n");
		$this->clients->detach($conn);
		print "Connection closed.\n";
	}
	public function onError(ConnectionInterface $conn, \Exception $e)
	{
		print "* hemorrhage: ".$e->getMessage()."\n";
		$conn->send(json_encode(array('ERR'=>$e->getMessage())));
		$conn->close();
	}
}

$server = IoServer::factory(
	new HttpServer(
		new WsServer(
			new Chat()
		)
	), 8080
);

$server->run();

