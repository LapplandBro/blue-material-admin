<?php

// SECURITY: this file was missing both the standard "no direct access" guard and any
// authentication check, letting anonymous visitors view internal SQL profiling data and,
// via ?reset=1, destructively wipe the profiling tables (unauthenticated DoS). Require the
// same admin session used elsewhere in HLstatsX (see admin.php / search-class.php).
if ( !defined('IN_HLSTATS') )
{
	die('Do not access this file directly.');
}
if (!isset($_SESSION['loggedin']) || $_SESSION['acclevel'] < 80) {
	die("Access denied.");
}

if(isset($_REQUEST['reset']) && $_REQUEST['reset']) {
	$db->query("delete from hlstats_sql_web_profile");
	$db->query("delete from hlstats_sql_daemon_profile");
	die("Stats reset.");
}

print("<h3>Web performance</h3>");
print("<p>top queries by # of times run<table><tr><td>origin</td><td>count</td><td>total time</td><td>avg time</td></tr>");
$result = $db->query("select *, (run_time/run_count) as avg_rt from hlstats_sql_web_profile order by run_count desc limit 20");
while ($rowdata = $db->fetch_array($result)) {
	print("<tr><td>$rowdata[source]</td><td>$rowdata[run_count]</td><td>$rowdata[run_time]</td><td>$rowdata[avg_rt]</td></tr>");
	
}
print("</table>");

print("<p>top queries by total time taken<table><tr><td>origin</td><td>count</td><td>total time</td><td>avg time</td></tr>");
$result = $db->query("select *, (run_time/run_count) as avg_rt from hlstats_sql_web_profile order by run_time desc limit 20");
while ($rowdata = $db->fetch_array($result)) {
	print("<tr><td>$rowdata[source]</td><td>$rowdata[run_count]</td><td>$rowdata[run_time]</td><td>$rowdata[avg_rt]</td></tr>");
	
}
print("</table>");

print("<p>top queries by avg runtime<table><tr><td>origin</td><td>count</td><td>total time</td><td>avg time</td></tr>");
$result = $db->query("select *, (run_time/run_count) as avg_rt from hlstats_sql_web_profile order by avg_rt desc limit 20");
while ($rowdata = $db->fetch_array($result)) {
	print("<tr><td>$rowdata[source]</td><td>$rowdata[run_count]</td><td>$rowdata[run_time]</td><td>$rowdata[avg_rt]</td></tr>");
	
}
print("</table>");


print("<hr>");

print("<h3>Daemon performance</h3>");
print("<p>top queries by # of times run<table><tr><td>origin</td><td>count</td><td>total time</td><td>avg time</td></tr>");
$result = $db->query("select *, (run_time/run_count) as avg_rt from hlstats_sql_daemon_profile order by run_count desc limit 20");
while ($rowdata = $db->fetch_array($result)) {
	print("<tr><td>$rowdata[source]</td><td>$rowdata[run_count]</td><td>$rowdata[run_time]</td><td>$rowdata[avg_rt]</td></tr>");
	
}
print("</table>");

print("<p>top queries by total time taken<table><tr><td>origin</td><td>count</td><td>total time</td><td>avg time</td></tr>");
$result = $db->query("select *, (run_time/run_count) as avg_rt from hlstats_sql_daemon_profile order by run_time desc limit 20");
while ($rowdata = $db->fetch_array($result)) {
	print("<tr><td>$rowdata[source]</td><td>$rowdata[run_count]</td><td>$rowdata[run_time]</td><td>$rowdata[avg_rt]</td></tr>");
	
}
print("</table>");

print("<p>top queries by avg runtime<table><tr><td>origin</td><td>count</td><td>total time</td><td>avg time</td></tr>");
$result = $db->query("select *, (run_time/run_count) as avg_rt from hlstats_sql_daemon_profile order by avg_rt desc limit 20");
while ($rowdata = $db->fetch_array($result)) {
	print("<tr><td>$rowdata[source]</td><td>$rowdata[run_count]</td><td>$rowdata[run_time]</td><td>$rowdata[avg_rt]</td></tr>");
	
}
print("</table>");

?>