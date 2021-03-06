<html>
	<head>
		<link href='http://fonts.googleapis.com/css?family=Actor' rel='stylesheet' type='text/css'/	>
		<link rel="stylesheet" href="http://ajax.googleapis.com/ajax/libs/jqueryui/1.7.2/themes/ui-lightness/jquery-ui.css" type="text/css" media="all" />

		<!-- Bootstrap core CSS -->
		<!-- link href="http://startbootstrap.com/templates/sb-admin/css/bootstrap.css" rel="stylesheet"/ -->
		<link href="//maxcdn.bootstrapcdn.com/bootstrap/3.2.0/css/bootstrap.min.css" rel="stylesheet"/>

		<link rel="stylesheet" href="//maxcdn.bootstrapcdn.com/font-awesome/4.1.0/css/font-awesome.min.css" />


		<!-- JavaScript -->
		<script src="http://code.jquery.com/jquery-1.10.2.min.js"></script>
		<script src="//cdnjs.cloudflare.com/ajax/libs/jqueryui/1.10.3/jquery-ui.min.js"></script>
		<script src="//cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.2.0/js/bootstrap.min.js"></script>
	</head>
<body>
<style>
.key { width:200px; display:block;float:left;clear:left}
</style>

<div class="container">
	<div class="page-wrapper">
		<div data-role="content">
			<h1>WEIRd</h1>
			<div class="row">
				<div class="col-md-2">
					<div class="panel panel-default connectpanel">
						<div class="panel-heading">
							<button class="btn connect">Connect</button>
						</div>
						<div class="panel-body">
							<div role="form">
								<label for="bean">Current Collection</label>
								<input type="text" name="bean" class="bean form-control" value="books">
							</div>
						</div>
					</div>
				</div>

				<div class="col-md-10">
					<div class="panel panel-info">
						<div class="panel-heading">
							<h3 class="panel-title">Arbitrary form fields to store in collection</h3>
							<small>* Add a couple (even if they are the same) and play with the collection</small>
						</div>
						<div class="panel-body">
							<form role="form" id="formA">
								<div class="form-group">
									<label for="name">Name</label>
									<input class="form-control" type="text" name="name" value="Leo Tolstoy ">
								</div>
								<div class="form-group">
									<label for="title">Title</label>
									<input class="form-control" type="text" name="title" value="War and peace">
								</div>
								<div class="form-group">
									<a class="SET btn btn-success" href="#SET"><i class="fa fa-plus"></i> ADD RECORD</a>
								</div>
							</form>
						</div>
						<div class="panel-footer">
							<small><b>NOTE:</b> You could add more form fields in the index.html and it will save them without requiring a schema change!</small>
						</div>
					</div>
				</div>
			</div>

			<!--
			<div class="row">
				<div class="col-md-6">
					<div class="panel panel-info">
						<div class="panel-heading">
							<h3 class="panel-title">Single record</h3>
						</div>
						<div class="panel-body">
							<form id="formB">
								<label for="id">RECORD ID</label> <input type="text" name="id" value="" size="10"> <a class="GET btn btn-sm btn-default" href="#GET"><i class="fa fa-arrow-down"></i> GET</a> <a class="SUB btn btn-sm btn-primary" href="#SUB"><i class="fa fa-link"></i> SUB</a> <a class="DEL btn btn-sm btn-danger" href="#DEL"><i class="fa fa-trash-o"></i> DEL</a>
							</form>
						</div>
						<div class="panel-footer">
							<small><b>NOTE:</b>Click "LIST" on the collection to see all the IDs</small>
						</div>
					</div>
				</div>
				-->
				<div class="col-md-6">
					<div class="panel panel-info">
						<div class="panel-heading">
							<h3 class="panel-title">Collection output will show up here</h3>
						</div>
						<div class="panel-body">
							<a class="GET btn btn-primary" href="#GET"><i class="fa fa-list"></i> LIST</a>
							<a class="POP btn btn-primary" href="#POP"><i class="fa fa-eject"></i> POP</a><br />
						</div>
						<div class="panel-footer">
							<small><b>NOTE:</b> POP will FIFO GET/DELETE one record from the TOP of the collection</small>
							<div id="output"></div>
						</div>
					</div>
				</div>
			</div>


		</div>
	</div>
</div>

</body>

<script>
	var weird;
	var dscount = 0 ;
	// FormA the data input form
	// FormB is the "ID" input form
	$(document).ready(function() {
		connect(); 
	});

	//$('.connect').on('click',function() { connect() });

	$('.SET').on('click',function() {
		doWeird('set',$('.bean').val(),JSON.stringify($('#formA').serializeArray()));
	});

	$('.DEL').on('click',function() {
		doWeird('del',$('.bean').val(),JSON.stringify($('#formB').serializeArray()));
	});

	$('.SUB').on('click',function() {
		doWeird('sub',$('.bean').val(),JSON.stringify($('#formB').serializeArray()));
	});

	$('.POP').on('click',function() {
		doWeird('pop',$('.bean').val(),JSON.stringify($('#formB').serializeArray()));
	});

	$('.GET').on('click',function() {
		doWeird('get',$('.bean').val(),JSON.stringify($('#formB').serializeArray()));
	});

	function doWeird(cmd, bean, data_payload)
	{
		$('#formB input').val('');
		if (typeof weird == 'undefined')
		{
			// This could be whatever, but for now:
			alert('Please click the "Connect" button.');
			return(false);
		}
		else
		{
			console.log('Performing: '+cmd+' '+bean, data_payload);
			weird.send(cmd.toUpperCase()+' '+bean+' '+data_payload);
			return(true);
		}
	}

	function perform_magic(message)
	{
		$('#formB input').attr('value','');
		var dataSet = '';
		var bean    = 'books';


		$.each(message, function(idx,dataSet)
		{
			if (idx =='ERR')
			{
				$('#output').text('Error: '+dataSet.message);
				return false;
			}

			if (idx == 'NEW')
			{
				doWeird('get',$('.bean').val(),JSON.stringify($('#formB').serializeArray()));
			}
			else if (idx == 'DEL')
			{
				//bean = dataSet.BEAN;
				console.log(dataSet.id);
				$('#'+bean+'panel_'+dataSet.id).remove();
			}
			else if (idx == 'OK')
			{
				var magic	= '';
				var obj 	= '';
				$.each(dataSet, function (i,obj) 
				{
					var record_id = '';
					// Get the record id
					$.each(obj, function(k,v) {
						if (k == 'id') record_id = v;
					});

					console.log($('#'+bean+'panel_'+record_id).length); 

					if ($('#'+bean+'panel_'+record_id).length) {
						$.each(obj, function(k,v) {
							// This is this particular record id
							$('#'+bean+'panel_'+record_id+' ._'+k).val(v);
							$('#'+bean+'panel_'+record_id+' ._'+k).fadeTo('slow',0.5,function() {
								console.log('#'+bean+'panel_'+record_id+' ._'+k+' = '+v);
							});
						});

					}
					else {
						// Maybe use some sort of templating system here for wigitization of data?
						magic += '<div class="panel" id="'+bean+'panel_'+record_id+'"><div class="panel-body">';
						magic += '<form role="form" id="magic_'+record_id+'" class="magic">'
						$.each(obj, function(k,v) 
						{
							magic += '<div class="form-group">';
							magic += '<label for="'+k+'">'+k+'</label>';
							magic += '<input class="form-control _'+k+'" name="'+k+'" value="'+v+'">';
							magic += '</div>';
						});
						magic += '<a class="UPDATE btn btn-default"     href="#SET" datatype="'+bean+'" id="mod_'+record_id+'" ><i class="fa fa-check"></i> SET</a>';
						magic += ' <a class="SUBSCRIBE btn btn-primary" href="#SUB" datatype="'+bean+'" id="sub_'+record_id+'"><i class="fa fa-link"></i> SUB</a>';
						magic += ' <a class="DELETE btn btn-danger"     href="#DEL" datatype="'+bean+'" id="del_'+record_id+'"><i class="fa fa-trash-o"></i> DEL</a>';
						magic += '</form></div></div>';
					}
				});
				if (magic.length) $('#output').append(magic);
			}
			else 
			{
				console.log('Nothing special here: ',idx,message);
			}
		});

		// For clicking sub on specific item in list
		$('.DELETE').on('click', function()
		{
			id = $(this).attr('id').split('_');
			var magic_form_id = 'form#magic_'+id[1];
			var magic_panel		= '#'+$(this).attr('datatype')+'panel_'+id[1];

			del_json = { "id" : id[1] };
			doWeird('del',$('.bean').val(), JSON.stringify(del_json));

			console.log('Magic Panel',magic_panel);
			$(magic_panel).remove();
		});

		// For clicking sub on specific item in list
		$('.SUBSCRIBE').on('click', function()
		{
			id = $(this).attr('id').split('_');
			subscribe_json = { "id" : id[1] };
			doWeird('sub',$('.bean').val(), JSON.stringify(subscribe_json));
		});

		// Clicking "SET" in the list
		$('.UPDATE').on('click',function() 
		{
			var id = $(this).attr('id').split('_');
			var magic_form_id = 'form#magic_'+id[1];
			var json = JSON.stringify($(magic_form_id).serializeArray());
			doWeird('set', $('.bean').val(), json);
		});

	}

	function connect()
	{
		weird = new WebSocket("ws://"+window.location.hostname+":8888");

		weird.onopen = function (event) {
			$('.connectpanel').css('background','lightgreen');
			$('.connect').css('background','lightgreen');
			$('.connect').text('Connected');
		};

		weird.onclose = function (event) {
			$('.connectpanel').css('background','none');
			$('.connect').css('background','lightgrey');
			$('.connect').text('Connect');
		}

		weird.onmessage = function (data, start, end)
		{
			console.log('Got message.', data, start, end);
			var message = JSON.parse(data.data);
			perform_magic(message);
		}

	}
</script>

</body>
