WEIRd
=====

WebSockets + Interface + RedbeanPHP = WEIRd

### See it here
[http://cyberteknix.com/weird/](http://cyberteknix.com/weird/)

### What is WEIRd?
Weird is a proof of concept REAL TIME pub/sub mechanism inspied by [METEOR](https://www.meteor.com/) that uses the mutable database ORM provided by [RedBeanPHP](http://www.redbeanphp.com) combined with HTML5 websockets provided by [ReactPHP.org](http://www.reactphp.org)
- Right now it acts like a simple stack, with SET, GET, POP, DELete and SUBscribe
- It also allows for "collections" of data (basically just tables)

### WHY?
- I wanted to dig into event driven PHP a bit more, and play with websockets.
- I really like RedBeanPHP. *it's genius.*

### How to start the example
- first, run the server - # php server.php
- browse to index.html
- click "Connect" (should turn green!)
- Add a couple of items to the provided collection "book" by typing it into the form and clicking "Add Record"
- Open a second browser window and click connect
- In the second browser click "list" under actions, you should see your data
- If you specify the ID in the ID field, and click "SUB" whenever you update the data of that particular subscription, it will automatically push the changes to all subscribers.

### Gotchas and NOTES
- Right now it is proof of concept, and does not currently support multi-select or multiple checkboxen.
- Only tested on FF and Chrome on LINUX.
- **Not documented well for consumption**

