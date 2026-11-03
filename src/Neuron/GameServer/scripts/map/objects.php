<?php
/**
 *  Dolumar engine, php/html MMORTS engine
 *  Copyright (C) 2009 Thijs Van der Schaeghe
 *  CatLab Interactive bvba, Gent, Belgium
 *  http://www.catlab.eu/
 *  http://www.dolumar.com/
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License along
 *  with this program; if not, write to the Free Software Foundation, Inc.,
 *  51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

/*
	map/objects: the moving objects (armies in Dolumar, ...) touching the posted regions,
	as JSON. Same regions[] POST body as map/region. Logged-in players only;
	anonymous visitors get 403 and an empty list.
*/

$regions = Neuron_Core_Tools::sanitizeMapRegions ($_POST['regions'] ?? null, 100);
$tiles = Neuron_Core_Tools::clampInt ($_GET['tiles'] ?? null, 1, 10, 10);

$result = $this->renderMovingObjects ($regions, $tiles, Neuron_GameServer::getPlayer (), NOW);

header ('Content-type: application/json');
http_response_code ($result['status']);
echo json_encode ($result['body'], JSON_INVALID_UTF8_SUBSTITUTE | JSON_HEX_TAG | JSON_HEX_AMP);
