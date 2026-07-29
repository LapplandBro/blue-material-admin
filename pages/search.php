<?php
/*
HLstatsX Community Edition - Real-time player and clan rankings and statistics
Copyleft (L) 2008-20XX Nicholas Hastings (nshastings@gmail.com)
http://www.hlxcommunity.com

HLstatsX Community Edition is a continuation of 
ELstatsNEO - Real-time player and clan rankings and statistics
Copyleft (L) 2008-20XX Malte Bayer (steam@neo-soft.org)
http://ovrsized.neo-soft.org/

ELstatsNEO is an very improved & enhanced - so called Ultra-Humongus Edition of HLstatsX
HLstatsX - Real-time player and clan rankings and statistics for Half-Life 2
http://www.hlstatsx.com/
Copyright (C) 2005-2007 Tobias Oetzel (Tobi@hlstatsx.com)

HLstatsX is an enhanced version of HLstats made by Simon Garner
HLstats - Real-time player and clan rankings and statistics for Half-Life
http://sourceforge.net/projects/hlstats/
Copyright (C) 2001  Simon Garner
            
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.

For support and installation notes visit http://www.hlxcommunity.com
*/

	global $game;
	
// Search
	require(PAGE_PATH . '/search-class.php');
	pageHeader
	(
		array ('Search'),
		array ('Search' => '')
	);
	$sr_query = $_GET['q'];
	// SECURITY/BUG: `X = foo() or 'player'` never assigns the fallback because `or` binds looser than
	// `=`; use `?:` so an empty/missing ?st= actually falls back to 'player' as intended.
	$sr_type = valid_request(strval($_GET['st']), 0) ?: 'player';
	// SECURITY: whitelist to a safe charset in addition to valid_request()'s htmlspecialchars() escaping,
	// since $sr_game is interpolated directly into SQL in search-class.php (defense in depth).
	$sr_game = preg_replace('/[^A-Za-z0-9_\-]/', '', valid_request(strval((isset($_GET['game'])) ? $_GET['game'] : $game), 0));
	$search = new Search($sr_query, $sr_type, $sr_game);
	$search->drawForm(array('mode' => 'search'));
	if ($sr_query || $sr_query == '0')
		$search->drawResults();
