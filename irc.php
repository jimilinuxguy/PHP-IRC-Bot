<?php
	require_once('class.bot.php');

	$Bot = new Bot( array('server' => 'irc.freenode.net', 'port' => 6667, 'nick' => 'jimi__', 'gecos' => 'Jimi!')  );
		$Bot->connect();
?>
