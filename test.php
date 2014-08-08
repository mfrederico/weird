<?php

$js = '[{"name":"author","value":"Leo Tolstoy "},{"name":"book","value":"War and peace"}]';

print_r(parseFormJson($js));

function parseFormJson($js)
{
	$formdata = json_decode($js,true);
	foreach($formdata as $fields)
	{
		$final[$fields['name']] = $fields['value'];
	}
	return($final);
}

