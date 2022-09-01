<?php
	include_once 'html_open.php';
?>


<div class="container">	
	<div id="rsErrorDiv" class="alert alert-info" role="alert" style="display:none">
		Message for immediate attention.
	</div>	

	<!-- Panel for Search -->
	<div class="panel panel-primary">
		<div class="panel-heading">
			<H4 class="panel-title">Select Current Check-in</H4>
		</div>
		<div class="panel-body">
			<form id="Search" method="get" action="#" class="form-horizontal">
				<div class="form-group">				
					<label for="checkin" class="col-sm-3 control-label">Current Check-ins</label>
					<div class="col-sm-9">
						<select id="checkin" name="checkin" class="form-control" required></select>
					</div>
				</div>

				<div class="form-group">				
					<label for="interval" class="col-sm-3 control-label">Refresh Interval</label>
					<div class="col-sm-9">
						<select id="interval" name="interval" class="form-control">
							<option value="10">10 seconds</option>
							<option value="20" selected="selected">20 seconds</option>
							<option value="30">30 seconds</option>
							<option value="60">1 minute</option>
						</select>
					</div>
				</div>

				<div class="form-group">
					<div class="col-sm-offset-3 col-sm-9">
						<button type="submit" class="btn btn-primary">Get Live Check-in Data</button>
						<button type="reset"  class="btn btn-default">Clear</button>
					</div>
				</div>
			</form>
		</div>
	</div>	
	
	<div id="timer" class="alert alert-info" role="alert" style="display:display">
		Display for Timer Interval
	</div>	
	
	
	<!-- Panel for displaying Live Check-in data -->
	<div class="panel panel-primary table-responsive">
		<div class="panel-heading">
			<H3 class="panel-title">Live Check-ins</H3>
		</div>

		<div class="panel-body">
<!--			<p><span class="label label-info">Maximum 500 records can display.</span></p>	-->
			<table id="datatable" class="table table-striped table-hover table-condensed" cellspacing="0" width="100%">
				<thead>
				<tr>
					<th>ID</th>
					<th>Area</th>
					<th>Roster</th>
					<th>Open</th>
					<th>Headcounts</th>
					<th>List</th>
				</tr>
				</thead>
				<tbody id="checkins">
				</tbody>
			</table>
		</div>
	</div>	
	

	<p></p>


	<div id="result" class="alert alert-danger" role="alert" style="display:none">
		<p id="message"></p>
	</div>	
	
	<div id="resultsuccess" class="alert alert-success" role="alert" style="display:none">
		<p id="messagesuccess"></p>
	</div>	
	


	<div class="alert alert-warning" role="alert" style="display:none;">
		Please add an email address <a href='mailto:giving@northlandchurch.net'>giving@northlandchurch.net</a> into your email list to avoid spam. 
	</div>
	


</div>

<?php
	include_once 'html_footer.php';
?>

	
<script>
'use strict';

///////////////////////////////////////////////////////////////////////
// 					Global variables
///////////////////////////////////////////////////////////////////////


var message		= "";
var table;					// variable for holding scheduled givings
var searchUrl	= "";		
var checkins 	= [];		// Recurring Gifts
var locURL = "https://check-ins.planningcenteronline.com/event_periods/";
var curPeriodId = 0;
var counter= 0;
var interval = 0;
var timer; 

$(document).ready(function () {
	
	// Get Today Events by starting up
	$.ajax({
		type: "GET",
		url: "get_today_events.php",
		dataType: "json",
	})
	.done(function(data) {
//		console.log(data);
		
		var jsonObj;
		try 
		{
			jsonObj = $.parseJSON(data);
		} 
		catch(err) 
		{
			var msg = "An error occured in parsing JSON: " + err + ".<BR />Contact <a href='mailto:giving@northlandchurch.net'>Administrator</a>.";
			$("#rsErrorDiv").html(msg);
			$("#rsErrorDiv").show();
		}

	
		if (jsonObj === false) 
		{
			alert("Something wrong happened! Report Administrator!");
			return false;
		}


		var events = jsonObj['data'];
		var checkin = '<option value="">Select One Below...</option>';
		for (var i=0; i<events.length; i++)
		{
			checkin += '<option value="' + events[i]['timeid'] + '" data-eventid="' + events[i]['id'] + '" data-periodid="' + events[i]['periodid'] + '">' + events[i]['displayname'] + '</option>';
		}
		
		$("#checkin").html(checkin);
	})
	.fail(function(jqXHR, textStatus) {
		var msg = "Request failed: " + textStatus + ".<BR />Contact <a href='mailto:keehong.pang@northlandchurch.net'>Administrator</a>.";
		$("#rsErrorDiv").html(msg);
		$("#rsErrorDiv").show();
	})
	.always(function() { });


	// DataTable Initialization
	table = $('#datatable').DataTable({
		ajax: {
			url: "get_live_check_in.php?step=init&eventid=&eventtimeid=&eventperiodid=",
			dataSrc: function(data) {
				var jsonObj = $.parseJSON(data);
				checkins 	= jsonObj['data'];

				return checkins;
			}
		},
		stateSave: 		true,
		scrollCollapse:	true,
//		scrollY:		"350px",	
//		lengthChange: 	true,
		select:		{
			style:	'single',
		},
		pageLength: 50,
		lengthMenu: [25, 50, 75, 100],

		language: 	{
			lengthMenu:		"Show _MENU_ records per page",
			infoFiltered:	"(filtered from _MAX_ total records)",
//			zeroRecords:	"",
		},

		columns: [
			{ data: "id" },
			{ data: "area" },
			{ data: "roster"},
			{ data: "open" },
			{ data: "pcount" },
			{ data: "plist" },
		],
		columnDefs: [
			{
				render: function (data, type, row) {
					return '<a href="' + locURL + curPeriodId + '/locations/' + row.id + '/edit">' + data + '</a>';
				},
				targets: 3
			},
			{ visible: false, targets: 0 },
			{ width: "80px", targets: 1},
			{ width: "150px", targets: 2},
			{ width: "50px", targets: 3},
			{ width: "30px", targets: 4},
		],
/*
		columnDefs: [
			{ width: "100px", "targets": 0},
			{ width: "200px", "targets": 1}
		],
*/		

/*		
		buttons: ['csv', 'excel', 'pdf', 'print',
			{
				extend: 'colvis',
				text:	'Colums'
			}
		],
		
		initComplete: function (){
			table.buttons().container().appendTo($("#datatable_wrapper .col-sm-6:eq(0)"));
		},
*/		
	});



	// Trigger proper method based on the form clicked
	$("form").submit(function(event) {
		event.preventDefault();

		// Hide result messages
		$("#result").hide();
		$("#resultsuccess").hide();

//		console.log(event.target.id);

		switch(event.target.id) {
			case "Search":
				processSearch();
				break;
			default:
				break;
		}

	});
	
	
});		// End for $(document).ready(


////////////////////////////////////////////////////////////////////////////////////////////////////////
//	Sending a search request and load the response to the table in the Scheduled Givings section
////////////////////////////////////////////////////////////////////////////////////////////////////////
function processSearch()
{
	stopLiveCheckIn();

	// Clear all sections
	clearResultDiv();				// Hide all Result related DIV

	var selected = $("#checkin").find('option:selected');
	var eventid	= selected.data("eventid")
	var eventperiodid	= selected.data("periodid")
	var eventtimeid	= $("#checkin").val();

	curPeriodId = eventperiodid;
	searchUrl	= "get_live_check_in.php?step=search&eventid=" + eventid + "&eventtimeid=" + eventtimeid + "&eventperiodid=" + eventperiodid;
	console.log (searchUrl);

	// Load a new search
	table.ajax.url(searchUrl).load();

	// Refresh Live Check-in data with the interval
	interval = $("#interval").val();
	counter = interval;

	timer = setInterval(startLiveCheckIn, 1000);
}


function startLiveCheckIn()
{
	var html = "";
	if (counter == 0) 
	{
		html = "Refreshing Check-in Data...";
		$("#timer").html(html);
		console.log(counter + ": " + searchUrl);

		counter = interval;
		table.ajax.url(searchUrl).load();
	}
	else 
	{
		html = "Refresh in " + counter + " seconds";
		$("#timer").html(html);
		counter--;
	}
	

}


function stopLiveCheckIn()
{
	clearInterval(timer);
}


///////////////////////////////////////////////////////////////////////////
//	Clear all Div for result display
///////////////////////////////////////////////////////////////////////////
function clearResultDiv()
{
	$("#dangerpayment").hide();
	$("#successpayment").hide();
	
	$("#dangerrecurring").hide();
	$("#successrecurring").hide();
}


</script>



</body>

</html>
