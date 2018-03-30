#!/usr/bin/php -q
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2018 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/* do NOT run this script through a web browser */
if (!isset($_SERVER['argv'][0]) || isset($_SERVER['REQUEST_METHOD'])  || isset($_SERVER['REMOTE_ADDR'])) {
	die('<br><strong>This script is only meant to run at the command line.</strong>');
}

/* We are not talking to the browser */
$no_http_headers = true;

include(dirname(__FILE__).'/../include/global.php');
include_once($config['base_path'].'/lib/cli.php');
include_once($config['base_path'].'/lib/api_automation_tools.php');
include_once($config['base_path'].'/lib/data_query.php');

/* Define exit codes */
Cli::RegisterHelpFunction('display_help');
Cli::RegisterExit('EXIT_INVALID_HOST',              "ERROR: You must supply a valid host-id for all hosts!\n");
Cli::RegisterExit('EXIT_INVALID_DATA_QUERY_ID',     "ERROR: You must supply a numeric data-query-id for all hosts!\n");
Cli::RegisterExit('EXIT_INVALID_REINDEX_METHOD',    "ERROR: You must supply a valid reindex method for all hosts!\n");
Cli::RegisterExit('EXIT_BAD_HOST_ID',               "ERROR: Unknown Host Id (%s)\n");
Cli::RegisterExit('EXIT_BAD_QUERY_ID',              "ERROR: Unknown Data Query Id (%s)\n");
Cli::RegisterExit('EXIT_BAD_QUERY_ASSOCIATION',     "ERROR: Data Query is already associated for host: (%s: %s) data query (%s: %s) reindex method (%s: %s)\n");
Cli::RegisterExit('EXIT_BAD_QUERY_ADD',             "ERROR: Data Query is already associated for host: (%s: %s) data query (%s: %s) reindex method (%s: %s)\n");

/* process calling arguments */

/* setup defaults */
$displayHosts 		= false;
$displayDataQueries = false;
$quietMode			= false;

unset($host_id);
unset($data_query_id);
unset($reindex_method);

$shortopts = "dVvHh";
$longopts = array(
	'debug',
	'host-id:',
	'data-query-id',
	'reindex-method',
	'version',
	'help',
	'list-hosts',
	'list-data-queries',
	'quiet'
);

$options = Cli::GetOpts($shortopts, $longopts, $remaining);
foreach ($options as $arg => $value) {
	switch ($arg) {
		case '-d':
			$debug = true;
			break;

		case '--host-id':
			$host_id = trim($value);
			if (!is_numeric($host_id)) {
				Cli::Exit(EXIT_INVALID_HOST);
			}
	
			break;

		case '--data-query-id':
			$data_query_id = $value;
			if (!is_numeric($data_query_id)) {				
				Cli::Exit(EXIT_INVALID_DATA_QUERY_ID);
			}

			break;

		case '--reindex-method':
			if (is_numeric($value) &&
				($value >= DATA_QUERY_AUTOINDEX_NONE) &&
				($value <= DATA_QUERY_AUTOINDEX_FIELD_VERIFICATION)) {
				$reindex_method = $value;
			} else {
				switch (strtolower($value)) {
					case 'none':
						$reindex_method = DATA_QUERY_AUTOINDEX_NONE;
						break;
					case 'uptime':
						$reindex_method = DATA_QUERY_AUTOINDEX_BACKWARDS_UPTIME;
						break;
					case 'index':
						$reindex_method = DATA_QUERY_AUTOINDEX_INDEX_NUM_CHANGE;
						break;
					case 'fields':
						$reindex_method = DATA_QUERY_AUTOINDEX_FIELD_VERIFICATION;
						break;
					default:
						Cli::Exit(EXIT_INVALID_REINDEX_METHOD);
				}
			}
			break;

		case '--version':
		case '-V':
		case '-v':
			display_version();
			Cli::Exit(EXIT_NORMAL);

		case '--help':
		case '-H':
		case '-h':
			display_help();
			Cli::Exit(EXIT_NORMAL);

		case '--list-hosts':
			$displayHosts = true;
			break;

		case '--list-data-queries':
			$displayDataQueries = true;
			break;

		case '--quiet':
			$quietMode = true;
			break;

		default:
			display_help();
			Cli::Exit(EXIT_ARGERR, $arg, true);
	}
}

/* list options, recognizing $quietMode */
if ($displayHosts) {
	$hosts = getHosts();
	displayHosts($hosts, $quietMode);
	Cli::Exit(EXIT_NORMAL);
}
if ($displayDataQueries) {
	$data_queries = getSNMPQueries();
	displaySNMPQueries($data_queries, $quietMode);
	Cli::Exit(EXIT_NORMAL);
}

/*
	* verify required parameters
	* for update / insert options
	*/
if (!isset($host_id)) {
	Cli::Exit(EXIT_INVALID_HOST);
}

if (!isset($data_query_id)) {		
	Cli::Exit(EXIT_INVALID_DATA_QUERY_ID);
}

if (!isset($reindex_method)) {
	Cli::Exit(EXIT_INVALID_REINDEX_METHOD);
}


/*
	* verify valid host id and get a name for it
	*/
$host_name = db_fetch_cell('SELECT hostname FROM host WHERE id = ' . $host_id);
if (!isset($host_name)) {		
	Cli::Exit(EXIT_BAD_HOST_ID, $host_id); 
}

/*
	* verify valid data query and get a name for it
	*/
$data_query_name = db_fetch_cell('SELECT name FROM snmp_query WHERE id = ' . $data_query_id);
if (!isset($data_query_name)) {
	Cli::Exit(EXIT_BAD_QUERY_ID, $data_query_id);
}

/*
	* Now, add the data query and run it once to get the cache filled
	*/
$exists_already = db_fetch_cell("SELECT host_id FROM host_snmp_query WHERE host_id=$host_id AND snmp_query_id=$data_query_id AND reindex_method=$reindex_method");
if ((isset($exists_already)) &&
	($exists_already > 0)) {
	Cli::Exit(EXIT_BAD_QUERY_ASSOCIATION, array($host_id, $host_name, $data_query_id, $data_query_name, $reindex_method, $reindex_types[$reindex_method]));
} else {
	db_execute('REPLACE INTO host_snmp_query 
		(host_id,snmp_query_id,reindex_method) 
		VALUES (' . 
			$host_id        . ',' . 
			$data_query_id  . ',' . 
			$reindex_method . ')');

	/* recache snmp data */
	run_data_query($host_id, $data_query_id);
}

if (is_error_message()) {
	Cli::Exit(EXIT_BAD_QUERY_ADD, array($host_id, $host_name, $data_query_id, $data_query_name, $reindex_method, $reindex_types[$reindex_method]));
} else {
	echo "Success - Host ($host_id: $host_name) data query ($data_query_id: $data_query_name) reindex method ($reindex_method: " . $reindex_types[$reindex_method] . ")\n";
	exit;
}

/*  display_version - displays version information */
function display_version() {
	$version = get_cacti_version();
	echo "Cacti Add Data Query Utility, Version $version, " . COPYRIGHT_YEARS . "\n";
}

function display_help() {
	display_version();

	echo "\nusage: add_data_query.php --host-id=[ID] --data-query-id=[dq_id] --reindex-method=[method] [--quiet]\n\n";
	echo "Required Options:\n";
	echo "    --host-id         the numerical ID of the host\n";
	echo "    --data-query-id   the numerical ID of the data_query to be added\n";
	echo "    --reindex-method  the reindex method to be used for that data query\n";
	echo "                      0|None   = no reindexing\n";
	echo "                      1|Uptime = Uptime goes Backwards\n";
	echo "                      2|Index  = Index Count Changed\n";
	echo "                      3|Fields = Verify all Fields\n\n";
	echo "List Options:\n";
	echo "    --list-hosts\n";
	echo "    --list-data-queries\n";
	echo "    --quiet - batch mode value return\n\n";
	echo "If the data query was already associated, it will be reindexed.\n\n";
}
