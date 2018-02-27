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
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
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

$no_http_headers = true;

include(dirname(__FILE__) . '/../include/global.php');
include_once($config['base_path'] . '/lib/cli.php');
include_once($config['base_path'] . '/lib/api_automation_tools.php');
include_once($config['base_path'] . '/lib/data_query.php');
include_once($config['base_path'] . '/lib/utility.php');
include_once($config['base_path'] . '/lib/sort.php');
include_once($config['base_path'] . '/lib/template.php');
include_once($config['base_path'] . '/lib/api_data_source.php');
include_once($config['base_path'] . '/lib/api_graph.php');
include_once($config['base_path'] . '/lib/snmp.php');
include_once($config['base_path'] . '/lib/data_query.php');
include_once($config['base_path'] . '/lib/api_device.php');

/* Define exit codes */
Cli::RegisterHelpFunction('display_help');
Cli::RegisterExit('EXIT_REGEX_BAD',           "ERROR: Regex specified '%s', is not a valid Regex!\n");
Cli::RegisterExit('EXIT_GRAPH_REINDEX',       "ERROR: You must supply a valid reindex method for this graph!\n");
Cli::RegisterExit('EXIT_GRAPH_LIST_HOST',     "ERROR: You must supply a valid --host-template-id before you can list its graph templates\nTry --list-graph-template-id --host-template-id=[ID]\n");
Cli::RegisterExit('EXIT_GRAPH_LIST_FIELD',    "ERROR: You must supply a graph-template-id before you can list its input fields\nTry --graph-template-id=[ID] --list-input-fields\n");
Cli::RegisterExit('EXIT_SNMP_QUERY_UNKNOWN',  "ERROR: Unknown snmp-query-id (%s)\nTry --list-snmp-queries\n");
Cli::RegisterExit('EXIT_SNMP_QUERY_TYPE',     "ERROR: Unknown snmp-query-type-id (%s)\nTry --snmp-query-id=%s --list-query-types\n");
Cli::RegisterExit('EXIT_HOST_UNKNOWN',        "ERROR: Unknown Host ID (%s)\nTry --list-hosts\n");
Cli::RegisterExit('EXIT_SNMP_VALUE_REGEX',    "ERROR: You can't supply --snmp-value and --snmp-value-regex at the same time\n");
Cli::RegisterExit('EXIT_SNMP_VALUE_FIELD',    "ERROR: number of --snmp-field and --snmp-value does not match\n");
Cli::RegisterExit('EXIT_SNMP_FIELD_REGEX',    "ERROR: number of --snmp-field (%s) and --snmp-value-regex (%s) does not match\n");
Cli::RegisterExit('EXIT_SNMP_FIELD_NEEDS',    "ERROR: You must supply a --snmp-value or --snmp-value-regex option with --snmp-field\n");
Cli::RegisterExit('EXIT_SNMP_FIELD_HOST',     "ERROR: Unknown snmp-field (%s) for host %s\nTry --list-snmp-fields\n");
Cli::RegisterExit('EXIT_SNMP_FIELD_VALUE',    "ERROR: Unknown snmp-value for field %s - %s\nTry --snmp-field=%s --list-snmp-values\n");
Cli::RegisterExit('EXIT_SNMP_FIELD_UNKNOWN',  "ERROR: You must supply an snmp-field before you can list its values\nTry --list-snmp-fields\n");
Cli::RegisterExit('EXIT_GRAPH_LIST_TEMPLATE', "ERROR: Unknown graph-template-id (%s)\nTry --list-graph-templates\n");
Cli::RegisterExit('EXIT_HOST_OR_TEMPLATE',    "ERROR: Must have at least a host-id and a graph-template-id\n");
Cli::RegisterExit('EXIT_INPUT_FIELD',         "ERROR: Unknown input-field (%s)\nTry --list-input-fields\n");
Cli::RegisterExit('EXIT_GRAPH_EXISTS',        "NOTE: Not Adding Graph - this graph already exists - graph-id: (%s) - data-source-id: (%s)\n");
Cli::RegisterExit('EXIT_DS_MORE',             "ERROR: For graph-type of 'ds' you must supply more options\n");
Cli::RegisterExit('EXIT_SNMP_FIELD_MISSING',  "ERROR: Could not find one of more snmp-fields '%s' ' with values (%s)\nTry --host-id=%s --list-snmp-fields\n");
Cli::RegisterExit('EXIT_GRAPH_TYPE_WRONG',    "ERROR: Graph Types must be set using --graph-type with either 'cg' or 'ds'\n");

/* process calling arguments */

/* setup defaults */
$graph_type    = '';
$templateGraph = array();
$dsGraph       = array();
$dsGraph['snmpFieldSpec']  = '';
$dsGraph['snmpQueryId']    = '';
$dsGraph['snmpQueryType']  = '';
$dsGraph['snmpField']      = array();
$dsGraph['snmpValue']      = array();
$dsGraph['snmpValueRegex'] = array();
$dsGraph['reindex_method'] = DATA_QUERY_AUTOINDEX_BACKWARDS_UPTIME;

$input_fields  = array();
$values['cg']  = array();

$hosts          = getHosts();
$graphTemplates = getGraphTemplates();

$graphTitle = '';
$cgInputFields = '';

$host_id     	= 0;
$template_id 	= 0;
$hostTemplateId = 0;
$force      	= 0;

$listHosts       		= false;
$listGraphTemplates 	= false;
$listSNMPFields  		= false;
$listSNMPValues  		= false;
$listQueryTypes  		= false;
$listSNMPQueries 		= false;
$listInputFields 		= false;

$quietMode       = false;

$shortopts = 'VvHh';

$longopts = array(
	'host-id:',
	'graph-type:',
	'graph-template-id:',

	'graph-title:',
	'host-template-id:',
	'input-fields:',
	'snmp-query-id:',
	'snmp-query-type-id:',
	'snmp-field:',
	'snmp-value:',
	'snmp-value-regex:',
	'reindex-method:',

	'list-hosts',
	'list-snmp-fields',
	'list-snmp-values',
	'list-query-types',
	'list-snmp-queries',
	'force',
	'quiet',
	'list-input-fields',
	'list-graph-templates',
	'version',
	'help'
);

$options = Cli::GetOpts($shortopts, $longopts, $remaining);

if (strlen($remaining)) {
	display_help();
	Cli::Exit(EXIT_ARGERR, $remaining);
}

foreach($options as $arg => $value) {
	$allow_multi = false;

	switch($arg) {
		case 'graph-type':
			$graph_type = $value;

			break;
		case 'graph-title':
			$graphTitle = $value;

			break;
		case 'graph-template-id':
			$template_id = $value;

			break;
		case 'host-template-id':
			$hostTemplateId = $value;

			break;
		case 'host-id':
			$host_id = $value;

			break;
		case 'input-fields':
			$cgInputFields = $value;

			break;
		case 'snmp-query-id':
			$dsGraph['snmpQueryId'] = $value;

			break;
		case 'snmp-query-type-id':
			$dsGraph['snmpQueryType'] = $value;

			break;
		case 'snmp-field':
			if (!is_array($value)) {
				$value = array($value);
			}

			$dsGraph['snmpField'] = $value;
			$allow_multi = true;

			break;
		case 'snmp-value-regex':
			if (!is_array($value)) {
				$value = array($value);
			}

			foreach($value as $item) {
				if (!validate_is_regex($item)) {
					Cli::Exit(EXIT_REGEX_BAD, $item);
				}
			}

			$dsGraph['snmpValueRegex'] = $value;
			$allow_multi = true;

			break;
		case 'snmp-value':
			if (!is_array($value)) {
				$value = array($value);
			}

			$dsGraph['snmpValue'] = $value;
			$allow_multi = true;

			break;
		case 'reindex-method':
			if (is_numeric($value) &&
				($value >= DATA_QUERY_AUTOINDEX_NONE) &&
				($value <= DATA_QUERY_AUTOINDEX_FIELD_VERIFICATION)) {
				$dsGraph['reindex_method'] = $value;
			} else {
				switch (strtolower($value)) {
					case 'none':
						$dsGraph['reindex_method'] = DATA_QUERY_AUTOINDEX_NONE;
						break;
					case 'uptime':
						$dsGraph['reindex_method'] = DATA_QUERY_AUTOINDEX_BACKWARDS_UPTIME;
						break;
					case 'index':
						$dsGraph['reindex_method'] = DATA_QUERY_AUTOINDEX_INDEX_NUM_CHANGE;
						break;
					case 'fields':
						$dsGraph['reindex_method'] = DATA_QUERY_AUTOINDEX_FIELD_VERIFICATION;
						break;
					default:
						Cli::Exit(EXIT_GRAPH_REINDEX);
				}
			}

			break;
		case 'list-hosts':
			$listHosts = true;

			break;
		case 'list-snmp-fields':
			$listSNMPFields = true;

			break;
		case 'list-snmp-values':
			$listSNMPValues = true;

			break;
		case 'list-query-types':
			$listQueryTypes = true;

			break;
		case 'list-snmp-queries':
			$listSNMPQueries = true;

			break;
		case 'force':
			$force = true;

			break;
		case 'quiet':
			$quietMode = true;

			break;
		case 'list-input-fields':
			$listInputFields = true;

			break;
		case 'list-graph-templates':
			$listGraphTemplates = true;

			break;
		case 'version':
		case 'V':
		case 'v':
			display_version();
			Cli::Exit(EXIT_NORMAL);
		case 'help':
		case 'H':
		case 'h':
			display_help();
			Cli::Exit(EXIT_NORMAL);
		default:
			display_help();
			Cli::Exit(EXIT_ARGERR, $arg, true);
	}

	if (!$allow_multi && isset($value) && is_array($value)) {
		Cli::Exit(EXIT_ARGDUP,$arg);
	}
}

if ($listGraphTemplates) {
	/* is a Host Template Id is given, print the related Graph Templates */
	if ($hostTemplateId > 0) {
		$graphTemplates = getGraphTemplatesByHostTemplate($hostTemplateId);
		if (!sizeof($graphTemplates)) {
			Cli::Exit(EXIT_GRAPH_LIST_HOST);
		}
	}

	displayGraphTemplates($graphTemplates, $quietMode);
	Cli::Exit(EXIT_NORMAL);
}


if ($listInputFields) {
	if ($template_id > 0) {
		$input_fields = getInputFields($template_id, $quietMode);
		displayInputFields($input_fields, $quietMode);
	} else {
		Cli:Exit(EXIT_GRAPH_LIST_FIELD);
	}
	Cli::Exit(EXIT_NORMAL);
}

if ($listHosts) {
	displayHosts($hosts, $quietMode);
	Cli::Exit(EXIT_NORMAL);
}

/* get the existing snmp queries */
$snmpQueries = getSNMPQueries();

if ($listSNMPQueries) {
	displaySNMPQueries($snmpQueries, $quietMode);
	Cli::Exit(EXIT_NORMAL);
}

/* Some sanity checking... */
if ($dsGraph['snmpQueryId'] != '') {
	if (!isset($snmpQueries[$dsGraph['snmpQueryId']])) {
		Cli::Exit(EXIT_SNMP_QUERY_UNKNOWN, $dsGraph['snmpQueryId']);
	}

	/* get the snmp query types for comparison */
	$snmp_query_types = getSNMPQueryTypes($dsGraph['snmpQueryId']);

	if ($listQueryTypes) {
		displayQueryTypes($snmp_query_types, $quietMode);
		Cli::Exit(EXIT_NORMAL);
	}

	if ($dsGraph['snmpQueryType'] != '') {
		if (!isset($snmp_query_types[$dsGraph['snmpQueryType']])) {
			Cli::Exit(EXIT_SNMP_QUERY_TYPE, array($dsGraph['snmpQueryType'], $dsGraph['snmpQueryId']));
		}
	}

	if (!($listHosts ||			# you really want to create a new graph
		$listSNMPFields || 		# add this check to avoid reindexing on any list option
		$listSNMPValues ||
		$listQueryTypes ||
		$listSNMPQueries ||
		$listInputFields)) {

		/* if data query is not yet associated,
		 * add it and run it once to get the cache filled */

		/* is this data query already associated (independent of the reindex method)? */
		$exists_already = db_fetch_cell_prepared('SELECT COUNT(host_id)
			FROM host_snmp_query
			WHERE host_id = ?
			AND snmp_query_id = ?',
			array($host_id, $dsGraph['snmpQueryId']));

		if ((isset($exists_already)) &&
			($exists_already > 0)) {
			/* yes: do nothing, everything's fine */
		} else {
			db_execute_prepared('REPLACE INTO host_snmp_query
				(host_id, snmp_query_id, reindex_method)
				VALUES (?, ?, ?)',
				array($host_id, $dsGraph['snmpQueryId'], $dsGraph['reindex_method']));

			/* recache snmp data, this is time consuming,
			 * but should happen only once even if multiple graphs
			 * are added for the same data query
			 * because we checked above, if dq was already associated */
			run_data_query($host_id, $dsGraph['snmpQueryId']);
		}
	}
}

/* Verify the host's existance */
if (!isset($hosts[$host_id]) || $host_id == 0) {
	Cli::Exit(EXIT_HOST_UNKNOWN, $host_id);
}

/* process the snmp fields */
if ($graph_type == 'dq' || $listSNMPFields || $listSNMPValues) {
	$snmpFields = getSNMPFields($host_id, $dsGraph['snmpQueryId']);

	if ($listSNMPFields) {
		displaySNMPFields($snmpFields, $host_id, $quietMode);
		Cli::Exit(EXIT_NORMAL);
	}

	$snmpValues = array();

	/* More sanity checking */
	/* Testing SnmpValues and snmpFields args */
	if ($dsGraph['snmpValue'] and $dsGraph['snmpValueRegex'] ) {
		Cli::Exit(EXIT_SNMP_VALUE_REGEX);
	}

	$nbSnmpFields      = sizeof($dsGraph['snmpField']);
	$nbSnmpValues      = sizeof($dsGraph['snmpValue']);
	$nbSnmpValuesRegex = sizeof($dsGraph['snmpValueRegex']);

	if ($nbSnmpValues) {
		if ($nbSnmpFields != $nbSnmpValues) {
			Cli::Exit(EXIT_SNMP_VALUE_FIELD);
		}
	} elseif ($nbSnmpValuesRegex) {
		if ($nbSnmpFields != $nbSnmpValuesRegex) {
			Cli::Exit(EXIT_SNMP_FIELD_REGEX, array($nbSnmpFields, $nbSnmpValuesRegex));
		}
	} else {
		Cli::Exit(EXIT_SNMP_FIELD_NEEDS);
	}

	$index_filter = 0;
	foreach($dsGraph['snmpField'] as $snmpField) {
		if ($snmpField != '') {
			if (!isset($snmpFields[$snmpField] )) {
				Cli::Exit(EXIT_SNMP_FIELD_HOST, array($dsGraph['snmpField'], $host_id));
			}
		}

		$snmpValues = getSNMPValues($host_id, $snmpField, $dsGraph['snmpQueryId']);

		$snmpValue      = '';
		$snmpValueRegex = '';

		if ($dsGraph['snmpValue']) {
			$snmpValue 	= $dsGraph['snmpValue'][$index_filter];
		} else {
			$snmpValueRegex = $dsGraph['snmpValueRegex'][$index_filter];
		}

		if ($snmpValue) {
			$ok = false;

			foreach ($snmpValues as $snmpValueKnown => $snmpValueSet) {
				if ($snmpValue == $snmpValueKnown) {
					$ok = true;
					break;
				}
			}

			if (!$ok) {
				Cli::Exit(EXIT_SNMP_FIELD_VALUE, array($snmpField, $snmpValue, $snmpField));
			}
		} elseif ($snmpValueRegex) {
			$ok = false;

			foreach ($snmpValues as $snmpValueKnown => $snmpValueSet) {
				if (preg_match("/$snmpValueRegex/i", $snmpValueKnown)) {
					$ok = true;
					break;
				}
			}

			if (!$ok) {
				Cli::Exit(EXIT_SNMP_FIELD_VALUE, array($snmpField, $snmpValue, $snmpField));
			}
		}

		$index_filter++;
	}

	if ($listSNMPValues)  {
		if (!$dsGraph['snmpField']) {
			Cli::Exit(EXIT_SNMP_FIELD_UNKNOWN);
		}

		if (sizeof($dsGraph['snmpField'])) {
			foreach($dsGraph['snmpField'] as $snmpField) {
				if ($snmpField = "") {
					Cli::Exit(EXIT_SNMP_FIELD_UNKNOWN);
				}

				displaySNMPValues($snmpValues, $host_id, $snmpField, $quietMode);
			}
		}

		Cli::Exit(EXIT_NORMAL);
	}
}

if (!isset($graphTemplates[$template_id])) {
	Cli::Exit(EXIT_GRAPH_LIST_TEMPLATE, $template_id);
}

if ((!isset($template_id)) || (!isset($host_id))) {
	Cli::Exit(EXIT_HOST_OR_TEMPLATE);
}

if ($cgInputFields != '') {
	$fields = explode(' ', $cgInputFields);
	if ($template_id > 0) {
		$input_fields = getInputFields($template_id, $quietMode);
	}

	if (sizeof($fields)) {
		foreach ($fields as $option) {
			$data_template_id = 0;
			$option_value = explode('=', $option);

			if (substr_count($option_value[0], ':')) {
				$compound = explode(':', $option_value[0]);
				$data_template_id = $compound[0];
				$field_name       = $compound[1];
			} else {
				$field_name       = $option_value[0];
			}

			/* check for the input fields existance */
			$field_found = false;
			if (sizeof($input_fields)) {
				foreach ($input_fields as $key => $row) {
					if (substr_count($key, $field_name)) {
						if ($data_template_id == 0) {
							$data_template_id = $row['data_template_id'];
						}

						$field_found = true;
						break;
					}
				}
			}

			if (!$field_found) {
				Cli::Exit(EXIT_INPUT_FIELD, $field_name);
			}

			$value = $option_value[1];
			$values['cg'][$template_id]['custom_data'][$data_template_id][$input_fields[$data_template_id . ':' . $field_name]['data_input_field_id']] = $value;
		}
	}
}

$returnArray = array();

if ($graph_type == 'cg') {
	$existsAlready = db_fetch_cell_prepared('SELECT id
		FROM graph_local
		WHERE graph_template_id = ?
		AND host_id = ?', array($template_id, $host_id));

	if ((isset($existsAlready)) &&
		($existsAlready > 0) &&
		(!$force)) {
		$dataSourceId  = db_fetch_cell_prepared('SELECT
			data_template_rrd.local_data_id
			FROM graph_templates_item, data_template_rrd
			WHERE graph_templates_item.local_graph_id = ?
			AND graph_templates_item.task_item_id = data_template_rrd.id
			LIMIT 1',
			array($existsAlready));

		Cli::Exit(EXIT_GRAPH_EXISTS, array($existsAlready, $dataSourceId));
	} else {
		$returnArray = create_complete_graph_from_template($template_id, $host_id, null, $values['cg']);
		$dataSourceId = '';
	}

	if ($graphTitle != '') {
		if (isset($returnArray['local_graph_id'])) {
			db_execute_prepared('UPDATE graph_templates_graph
				SET title_cache = ?
				WHERE local_graph_id = ?',
				array($graphTitle, $returnArray['local_graph_id']));

			update_graph_title_cache($returnArray['local_graph_id']);
		}
	}

	if (is_array($returnArray) && sizeof($returnArray)) {
		if (sizeof($returnArray['local_data_id'])) {
			foreach($returnArray['local_data_id'] as $item) {
				push_out_host($host_id, $item);

				if ($dataSourceId != '') {
					$dataSourceId .= ', ' . $item;
				} else {
					$dataSourceId = $item;
				}
			}
		}

		/* add this graph template to the list of associated graph templates for this host */
		db_execute_prepared('REPLACE INTO host_graph
			(host_id, graph_template_id) VALUES
			(?, ?)',
			array($host_id , $template_id));

		echo 'Graph Added - graph-id: (' . $returnArray['local_graph_id'] . ") - data-source-ids: ($dataSourceId)\n";
	} else {
		echo "Graph Not Added due to whitelist check failure.\n";
	}
} elseif ($graph_type == 'ds') {
	if (($dsGraph['snmpQueryId'] == '') || ($dsGraph['snmpQueryType'] == '') || (sizeof($dsGraph['snmpField']) == 0) ) {
		Cli::Exit(EXIT_DS_MORE, null, true);
	}

	$snmp_query_array = array();
	$snmp_query_array['snmp_query_id']       = $dsGraph['snmpQueryId'];
	$snmp_query_array['snmp_index_on']       = get_best_data_query_index_type($host_id, $dsGraph['snmpQueryId']);
	$snmp_query_array['snmp_query_graph_id'] = $dsGraph['snmpQueryType'];

	$req = 'SELECT distinct snmp_index
		FROM host_snmp_cache
		WHERE host_id=' . $host_id . '
		AND snmp_query_id=' . $dsGraph['snmpQueryId'];

	$index_snmp_filter = 0;
	if (sizeof($dsGraph['snmpField'])) {
		foreach ($dsGraph['snmpField'] as $snmpField) {
			$req  .= ' AND snmp_index IN (
				SELECT DISTINCT snmp_index FROM host_snmp_cache WHERE host_id=' . $host_id . ' AND field_name = ' . db_qstr($snmpField);

			if (sizeof($dsGraph['snmpValue'])) {
				$req .= ' AND field_value = ' . db_qstr($dsGraph['snmpValue'][$index_snmp_filter]). ')';
			} else {
				$req .= ' AND field_value LIKE "%' . addslashes($dsGraph['snmpValueRegex'][$index_snmp_filter]) . '%")';
			}
			$index_snmp_filter++;
		}
	}

	$snmp_indexes = db_fetch_assoc($req);

	if (sizeof($snmp_indexes)) {
		foreach ($snmp_indexes as $snmp_index) {
			$snmp_query_array['snmp_index'] = $snmp_index['snmp_index'];

			$existsAlready = db_fetch_cell_prepared('SELECT id
				FROM graph_local
				WHERE graph_template_id = ?
				AND host_id = ?
				AND snmp_query_id = ?
				AND snmp_index = ?',
				array($template_id, $host_id, $dsGraph['snmpQueryId'], $snmp_query_array['snmp_index']));

			if (isset($existsAlready) && $existsAlready > 0) {
				if ($graphTitle != '') {
					db_execute_prepared('UPDATE graph_templates_graph
						SET title_cache = ?
						WHERE local_graph_id = ?',
						array($graphTitle, $existsAlready));

					update_graph_title_cache($existsAlready);
				}

				$dataSourceId = db_fetch_cell_prepared('SELECT
					data_template_rrd.local_data_id
					FROM graph_templates_item, data_template_rrd
					WHERE graph_templates_item.local_graph_id = ?
					AND graph_templates_item.task_item_id = data_template_rrd.id
					LIMIT 1',
					array($existsAlready));

				echo "NOTE: Not Adding Graph - this graph already exists - graph-id: ($existsAlready) - data-source-id: ($dataSourceId)\n";

				continue;
			}

			$isempty = array(); /* Suggested Values are not been implemented */

			$returnArray = create_complete_graph_from_template($template_id, $host_id, $snmp_query_array, $isempty);

			if ($returnArray !== false) {
				if ($graphTitle != '') {
					db_execute_prepared('UPDATE graph_templates_graph
						SET title_cache = ?
						WHERE local_graph_id = ?',
						array($graphTitle, $returnArray['local_graph_id']));

					update_graph_title_cache($returnArray['local_graph_id']);
				}

				$dataSourceId = db_fetch_cell_prepared('SELECT
					data_template_rrd.local_data_id
					FROM graph_templates_item, data_template_rrd
					WHERE graph_templates_item.local_graph_id = ?
					AND graph_templates_item.task_item_id = data_template_rrd.id
					LIMIT 1',
					array($returnArray['local_graph_id']));

				foreach($returnArray['local_data_id'] as $item) {
					push_out_host($host_id, $item);

					if ($dataSourceId != '') {
						$dataSourceId .= ', ' . $item;
					} else {
						$dataSourceId = $item;
					}
				}

				echo 'Graph Added - graph-id: (' . $returnArray['local_graph_id'] . ") - data-source-ids: ($dataSourceId)\n";
			} else {
				echo "Graph Not Added due to whitelist check failure.\n";
			}
		}
	} else {
		$err_msg = 'ERROR: Could not find one of more snmp-fields ' . 
		$err_fields = implode(',', $dsGraph['snmpField']);
		if (sizeof($dsGraph['snmpValue'])) {
			$err_values = implode(',',$dsGraph['snmpValue']);
		} else {
			$err_values = implode(',',$dsGraph['snmpValueRegex']);
		}
		Cli::Exit(EXIT_SNMP_FIELD_MISSING, array($err_fields, $err_vales, $host_id));
	}
} else {
	Cli::Exit(EXIT_GRAPH_TYPE_WRONG, null, true);
}
Cli::Exit(EXIT_NORMAL);

/*  display_version - displays version information */
function display_version() {
	$version = get_cacti_version();
	echo "Cacti Add Graphs Utility, Version $version, " . COPYRIGHT_YEARS . "\n";
}

function display_help(int $exit_code = 0) {
	display_version();

	global $graph_type;

	echo "\nusage: add_graphs.php --graph-type=[cg|ds] --graph-template-id=[ID]\n";
	echo "    --host-id=[ID] [--graph-title=title] [graph options] [--force] [--quiet]\n\n";
	echo "Cacti utility for creating graphs via a command line interface.  This utility can\n";
	echo "create both Data Query (ds) type Graphs as well as Graph Template (cg) type graphs.\n\n";
	if (strlen($graph_type)) {
		if ($graph_type == 'cg') {
			echo "For Non Data Query (cg) Graphs:\n";
			echo "    [--input-fields=\"[data-template-id:]field-name=value ...\"] [--force]\n\n";
			echo "    --input-fields  If your data template allows for custom input data, you may specify that\n";
			echo "                    here.  The data template id is optional and applies where two input fields\n";
			echo "                    have the same name.\n";
			echo "    --force         If you set this flag, then new cg graphs will be created, even though they\n";
			echo "                    may already exist\n\n";
		}

		if ($graph_type == 'ds') {
			echo "For Data Query (ds) Graphs:\n";
			echo "    --snmp-query-id=[ID] --snmp-query-type-id=[ID] --snmp-field=[SNMP Field] \n";
			echo "                         --snmp-value=[SNMP Value] | --snmp-value-regex=[REGEX]\n";
			echo "    [--graph-title=S]       Defaults to what ever is in the Graph Template/Data Template.\n";
			echo "    [--reindex-method=N]    The reindex method to be used for that data query.\n";
			echo "                            NOTE: If Data Query is already associated, the reindex method will NOT be changed.\n\n";
			echo "    Valid --reindex-methos include\n";
			echo "        0|None   = No reindexing\n";
			echo "        1|Uptime = Uptime goes Backwards (Default)\n";
			echo "        2|Index  = Index Count Changed\n";
			echo "        3|Fields = Verify all Fields\n\n";
			echo "    NOTE: You may supply multiples of the --snmp-field and --snmp-value | --snmp-value-regex arguments.\n\n";
		}
	} else {
		echo "Specify Graph Type:\n";
		echo "    --graph-type=cg   Non-Data Query Graph Type\n";
		echo "    --graph-type=ds   Data Query Graph Type\n\n";
	}

	echo "List Options:\n";
	echo "    --list-hosts\n";
	echo "    --list-graph-templates [--host-template-id=[ID]]\n";
	echo "    --list-input-fields --graph-template-id=[ID]\n";
	echo "    --list-snmp-queries\n";
	echo "    --list-query-types  --snmp-query-id [ID]\n";
	echo "    --list-snmp-fields  --host-id=[ID] [--snmp-query-id=[ID]]\n";
	echo "    --list-snmp-values  --host-id=[ID] [--snmp-query-id=[ID]] --snmp-field=[Field]\n\n";
}
