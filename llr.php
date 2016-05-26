<?php
include_once('vendor/autoload.php');
//include_once('vendor/rbsock/src/rb.php');


use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

if (file_exists('../weird_database.php')) include_once('../weird_database.php');
else R::setup('sqlite:local.sqlite','test','test');

class Chat implements MessageComponentInterface {

	var $valid_commands = array('SET', 'GET', 'DEL', 'POP', 'SUB', 'ADD');

	public $keeper	= array();
	public $watcher	= array();

	public $subscribers;
	public $subscriptions;
	public $inventory	= array();
	protected $clients;
	protected $notify;

	static public function csend($conn,$data) { 
		print "\n".print_r($data,true)."\n".date('Y-m-d H:i:s')."------------------\n";
		$conn->send($data);
	}
	
	public function getClients()
	{
		return($clients);
	}

	public function __construct() {
		$this->clients = new \SplObjectStorage;
		print "Grabbing inventory: ";
		$load = json_decode(file_get_contents('https://mylularoe.com/llrapi/v3/get-inventory/'),true);
		print "DONE!\n";

		foreach($load as $itemname=>$contents) {
			print "Item: {$itemname}\n";
			$this->inventory[$itemname]['itemid']	= md5($itemname);
			$this->inventory[$itemname]['img']		= $contents['thumb'];
			foreach($contents['quantities'] as $size=>$data) {
				$this->inventory[$itemname]['sizes'][$size]['count']	= $data['count'];
				$this->inventory[$itemname]['sizes'][$size]['id']		= $data['inv_id'];
				$this->inventory[$itemname]['sizes'][$size]['prod_id']	= $data['product_id'];
				$this->inventory[$itemname]['sizes'][$size]['price']	= $data['price'];

				$this->watcher[$data['inv_id'].'-'.$data['product_id']]['count'] = $data['count'];
			}
		}
		print "Loaded ".count($this->inventory)." items!\n";
		return($this);
	}

	public function onOpen(ConnectionInterface $conn) {
		print "Connection open: {$conn->resourceId}\n";
		$this->clients->attach($conn);

		//self::csend($conn,json_encode(array('message'=>'CONNECTED')));

		self::csend($conn,json_encode(array('inventory'=>$this->inventory)));
	}

    public function pushNotify(ConnectionInterface $from, $msg) {
        $numRecv = count($this->clients) - 1;
        echo sprintf('Connection %d sending message "%s" to %d other connection%s' . "\n"
            , $from->resourceId, $msg, $numRecv, $numRecv == 1 ? '' : 's');

        foreach ($this->clients as $client) {
            if ($from !== $client) {
                // The sender is not the receiver, send to each client connected
                //$client->send($msg);
                self::csend($client,$msg);
            }
        }
    }

	public function onMessage(ConnectionInterface $from, $msg)
	{
		print("FROM {$from->resourceId} :: ".print_r($msg,true)."\n");
		$data = explode(' ',$msg, 3);
		$jacket = json_decode($data[2]);
		$set = (object) array();

		if ($data['0'] == 'POP') {
			$jkt = $jacket[0];
			$this->watcher[$jkt][$from->resourceId] = -intval($jacket[1]);
			$this->keeper[$from->resourceId][$jkt]	= $jacket[1];
			$remaining = array_sum($this->watcher[$jacket[0]]);
			print_r("Total Remaining: {$remaining}\n");

			// Tell everyone
			$set->set = array($jacket[0],$remaining);
			$this->pushNotify($from, json_encode($set));

			// Tell yourself
			$set->set[2] = $jacket[1];
			self::csend($from,json_encode($set));
		}

	}

	public function onClose(ConnectionInterface $conn)
	{
		$set = (object) array();
		print_r("DISCONNECT: {$conn->resourceId}\n");
		foreach($this->keeper[$conn->resourceId] as $jacket=>$cnt) {
			unset($this->watcher[$jacket][$conn->resourceId]);
			$remaining = array_sum($this->watcher[$jacket]);
			print_r("Total Remaining: {$remaining}\n");
			$set->set = array($jacket,$remaining);
			$this->pushNotify($conn, json_encode($set));
		}
		unset($this->keeper[$conn->resourceId]);

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
	), 8181
);

$server->run();
