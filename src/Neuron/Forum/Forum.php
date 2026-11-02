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

define ('HORNET_DEV', ($_SERVER['DOCUMENT_ROOT'] == 'C:/xampp/xampp/Web'));//needed in copied section

function q ($stuff, $label = '') {
	if ($label != '') { $label = "[ $label ]<br />"; }
	if($_SERVER['REMOTE_ADDR'] == '82.28.27.211') { print '<pre>'.$label; var_dump ($stuff); print '</pre>';}
}

function safen(&$data) {
	//if using safen & dbPack, always use safen($stuff); dbPack($stuff); - there be errors otherwise.  _NEVER_ try safen(dbpack($stuff)), as they both modify the data by reference the outer one will always fail - and that'd usually mean no SQL escaping would take place, as dbPack(safen(stuff)) would return mangled text it'd be spotted and thus not used in that order.
	//$data = mysqli_real_escape_string($data);
	$db = Neuron_Core_Database::__getInstance ();
	$data = $db->escape ($data);
}

class numbers {

	public $start;
	public $curpage;
	public $last;

	public function __construct() {
		$this->start = 0;
		$this->curpage = 0;
		$this->last = 0;
	}

	public function draw($section, $label=null, $other=null, $top=null) {//viewBoard, etc
		$pagelist_action = "action=$section";
		if ($label != null) { $pagelist_action .= "&amp;$label=$other"; }

		
		
		//include('blocks/pagelist.tpl');
		//windowAction (this, 'action=viewTopic&topic=5');
		$this->last = intval($this->last);
		$pageno = $this->curpage;
if ($this->last > 1) {

	if ($top == null) { print '<div id="pages">'; } else { print '<div id="pages" class="top">'; }

	if ($pageno == 1) {
		print ' <strong class="cap">&laquo;</strong> <strong class="pcap">&lt;</strong> ';
	} else {
		print "<a href=\"#\" class=\"cap\" onclick=\"windowAction (this, '$pagelist_action&amp;pageNumber=0')\">&laquo;</a> ";
	$prevpage = $pageno-1;
		print "<a href=\"#\" class=\"pcap\" onclick=\"windowAction (this, '$pagelist_action&amp;pageNumber=$prevpage')\">&lt;</a> ";
	}
	if ($this->last <= 7) { 
		$lcap = 1;
		$rcap = $this->last;
	} else {
		if ($pageno + 3 >= $this->last) {
			$lcap = $this->last - 6;
			$rcap = $this->last;
		} elseif ($pageno - 3 <= 1) {
			$lcap = 1;
			$rcap = 7;
		} else {
			$lcap = $pageno - 3;
			$rcap = $pageno + 3;
		}
	}
	$X = $lcap;
	print '<span id="cw">';
	while ($X <= $rcap) {
		$curr = ($X == $pageno) ? ' class="current"' : '';
		print "<a href=\"#\"$curr onclick=\"windowAction (this, '$pagelist_action&amp;pageNumber=$X')\">$X</a>";
		$X++;
	}
	print '</span>';
	
	if ($pageno == $this->last) {
		print " <strong class=\"pcap\">&gt;</strong> <strong class=\"cap\">&raquo;</strong> ";
	} else {
	$nextpage = $pageno+1;
		print "<a href=\"#\" class=\"pcap\" onclick=\"windowAction (this, '$pagelist_action&amp;pageNumber=$nextpage')\">&gt;</a> ";
		print "<a href=\"#\" onclick=\"windowAction (this, '$pagelist_action&amp;pageNumber={$this->last}')\" class=\"cap\">&raquo;</a> ";
	}
	print '</div>';
}


	}

}
global $theNumbers;
$theNumbers = new numbers();

function getName($ID) {
	//left join?
	if (HORNET_DEV) {
		return 'fred';
	} elseif ($ID === 0) {
		return 'Guest';
	} else {
		$objPlayer = Neuron_GameServer::getPlayer ($ID);
		$nick = Neuron_Core_Tools::output_varchar($objPlayer->getNickname());
		return ($nick == '') ? 'Guest' : $nick;
	}
}

/*
	Custom database clase.
*/
class fdbClass
{
	private $objDB;

	public function __construct() 
	{
		$this->objDB = Neuron_Core_Database::__getInstance ();
	}
	
	private function toArray ($result)
	{
		$out = array ();
		foreach ($result as $k => $v)
		{
			$out[$k] = $v;
		}
		
		return $out;
	}

	// Query
	public function q($sql) {
		//return mysqli_query ($sql);
		$this->objDB->customQuery ($sql);
		
		return true;
	}

	// Insert
	public function i ($sql) {
		$this->objDB->customQuery ($sql);
		return $this->objDB->getInsertId ();
	}
	
	public function x($sql) {
		return $this->toArray ($this->objDB->getDataFromQuery ($this->objDB->customQuery ($sql)));
	}
}

class scopeBusterClass
{
	public $input;
	public function __construct($forum) {
		$this->forum = $forum;
		$this->input = '';
	}
}


class textBits
{
	public static function toBB (&$text) {
		$bbcode_regex = array
		(
			0 => '/<span style="font-weight:bold;">(.+?)<\/span>/s',
			1 => '/<span style="font-style:italic;">(.+?)<\/span>/s',
			2 => '/<span style="text-decoration:underline;">(.+?)<\/span>/s',
			3 => '/<strong>Quoting (.+?):<\/strong><div style\="margin:0px 10px;padding:5px;background-color:#222222;border:1px dotted #CCCCCC;width:80%;"><em>(.+?)<\/em><\/div>/s',
			4 => '/<strong>Quote:<\/strong><div style\="margin:0px 10px;padding:5px;background-color:#222222;border:1px dotted #CCCCCC;width:80%;"><em>(.+?)<\/em><\/div>/s',
			6 => '/<a href\="(.+?)\">(.+?)<\/a>/s',
			7 => '/<img src\="(.+?)" alt\="User submitted image" \/>/s',
			8 => '/<span style\="color:(.+?);">(.+?)<\/span>/s',
			9 => '/<span style\="font-size:(.+?);">(.+?)<\/span>/s'
		);
		
		$bbcode_regex[] = '/<h1>(.+?)<\/h1>/s';
		$bbcode_regex[] = '/<h2>(.+?)<\/h2>/s';
		$bbcode_regex[] = '/<h3>(.+?)<\/h3>/s';
		$bbcode_regex[] = '/<h4>(.+?)<\/h4>/s';
		$bbcode_regex[] = '/<h5>(.+?)<\/h5>/s';
		$bbcode_regex[] = '/<h6>(.+?)<\/h6>/s';


		$bbcode_replace = array
		(
			0 => '[b]$1[/b]',
			1 => '[i]$1[/i]',
			2 => '[u]$1[/u]',
			3 => '[quote=$1]$2[/quote]',
			4 => '[quote]$1[/quote]',
			6 => '[url=$1]$2[/url]',
			7 => '[img]$1[/img]',
			8 => '[col=$1]$2[/col]',
			9 => '[size=$1]$2[/size]'
		);
		
		$bbcode_replace[] = '[h1]$1[/h1]';
		$bbcode_replace[] = '[h2]$1[/h2]';
		$bbcode_replace[] = '[h3]$1[/h3]';
		$bbcode_replace[] = '[h4]$1[/h4]';
		$bbcode_replace[] = '[h5]$1[/h5]';
		$bbcode_replace[] = '[h6]$1[/h6]';

		$text = preg_replace($bbcode_regex, $bbcode_replace, $text);
	}

	public static function toHTML (&$text) {
		return Neuron_Core_Tools::output_text ($text);
	}

	public static function dbPack (&$text) {
 		/*
		$myText = htmlspecialchars($text);//prevents most script injection etc, I think IE will still act on some inline JS events that aren't quoted, but there's not much more that can be done, except perhaps scan all inputs for onload, onmouseover etc.  Depends how paranoid you are :)
		$StartIndex = 0;
		$Result = '';
		while ($StartIndex < strlen($myText)) {
			$EndIndex = strpos($myText, "\r\n\r\n", $StartIndex + 1);
			if ($EndIndex === false) {
				$EndIndex = strlen($myText);
			}
	
			$Paragraph = substr($myText, $StartIndex, $EndIndex - $StartIndex);
			$Paragraph = str_replace ("\r\n", '<br />', $Paragraph);
	
			$Result .= "<p>$Paragraph</p>";
	
			$StartIndex = $EndIndex + 4;
		}
		$text = $Result;
		*/
		$db = Neuron_Core_Database::__getInstance ();
		return $db->escape ($text);
	}

	//used when getting db content back into a textbox again
	public static function dbUnpack (&$text) {
		/*
		$curtext = explode('[~nl~]', str_replace(array('<p>', '</p>', '<br />'), '[~nl~]', $text));
		$X = array_pop($curtext);
		if ($X != '') { array_push($curtext, $X); }
		$text = implode("\r", $curtext);
		*/

		$text = Neuron_Core_Tools::output_varchar ($text);
		return $text;
	}
}

class forumBoard
{
	public function __construct ($parent) {
		$this->master = $parent;		
	}

	function getArray() {
		global $fdb;
		$boardList = $fdb->x("SELECT * FROM `forum_boards` WHERE `forum_id`='$this->compID'");
	}

	function addStats($boardID, $topicID, $postID, $posterID, $wasNewPost=false, $wasNewTopic=false, $topicTitle=NULL) {
		//poster id required; allows other parts to use this for re-statting with non-current user as a param
		//stuff must be escaped etc before being used here
		global $fdb;
		
		$TC = ($wasNewTopic) ? ', `topic_count`=`topic_count`+1' : '';
		$PC = ($wasNewPost) ? ', `post_count`=`post_count`+1 ' : '';
		
		if($topicTitle == NULL) {
			//get it
			$topicTitle = $this->master->topic->getTitle($topicID);
		}
		safen($topicTitle);
		$fdb->q
		("
			UPDATE
				forum_boards
			SET
				`last_post`=".time().",
				`last_topic_id`='$topicID',
				`last_topic_title`='$topicTitle' ,
				`last_post_id`='$postID',
				`last_poster`='$posterID'{$PC}{$TC}
			WHERE
				`ID`='$boardID'
		");

		//q($posterID, 'addstat poster id');
	}

	function reStat($boardID) {
		global $fdb;

		$topicCount = $fdb->x("SELECT COUNT(*) FROM `forum_topics` WHERE `board_id`='$boardID'");
		
		$topicCount = array_pop ($topicCount);
		$topicCount = array_pop ($topicCount);

		$postCount = $fdb->x("SELECT COUNT(*) FROM `forum_posts` WHERE `board_id`='$boardID'");
		$postCount = array_pop($postCount);
		$postCount = array_pop($postCount);
		//q($topicCount);
		//q($postCount);
		
/*		//get current
		$postID = $fdb->x
		("
			SELECT
				`last_post_id`
			FROM
				`forum_boards`
			WHERE
				`ID`='$boardID'
		");//*/
		
		//if (!$this->master->post->isPost($postID[0]['last_post_id'])) {
			$newPost = $fdb->x
			("
				SELECT
					*
				FROM
					`forum_posts`
				WHERE
					`forum_id`='{$this->master->compID}'
				ORDER BY
					`created` DESC
				LIMIT 1
			");

			//this loads data into the master->post object
			
		//}
		//an error can occur here and prevent redirection if the very last post on a board has been removed, but it's nothing serious, and will hardly ever occur
		
		//q($newPost);
		$lastpost = isset($newPost[0]['created']) ? $newPost[0]['created'] : '';
		$lasttopicid = isset($newPost[0]['topic_id']) ? $newPost[0]['topic_id'] : '';
		$lasttopictitle = isset($newPost[0]['topic_id']) ? $this->master->topic->getTitle($newPost[0]['topic_id']) : '';
		$lastpostid = isset($newPost[0]['ID']) ? $newPost[0]['ID'] : '';
		$lastposter = isset($newPost[0]['poster_id']) ? $newPost[0]['poster_id'] : '';
		//q($lastpost);
		//q($lasttopicid);
		//q($lasttopictitle);
		//q($lastpostid);
		//q($lastposter);
		
		safen ($lasttopictitle);
		safen ($lastposter);
		
		$fdb->q
		("
			UPDATE
				forum_boards
			SET
				`last_post`='$lastpost',
				`last_topic_id`='$lasttopicid',
				`topic_count`=$topicCount,
				`last_topic_title`='$lasttopictitle',
				`last_post_id`='$lastpostid',
				`post_count`=$postCount,
				`last_poster`='$lastposter'
			WHERE
				`ID`='$boardID'
		");
	}


	function isVisible($board) {
		global $fdb;
		
		$result = $fdb->x
		("
			SELECT
				`private`,
				`title`
			FROM
				`forum_boards`
			WHERE
				`ID`='$board' AND
				`forum_id`='{$this->master->compID}'
		");
		
		$this->title = $result[0]['title'];
		
		return 
			($result[0]['private'] == 0) ||
			(($result[0]['private'] == 1) && ($this->master->isOmni)) ||
			(($result[0]['private'] == 1) && ($this->master->isMod))
		;

	}
	
	function guestable($board) {
		global $fdb;
		
		$result = $fdb->x
		("
			SELECT
				`guestable`
			FROM
				`forum_boards`
			WHERE
				`ID`='$board' AND
				`forum_id`='{$this->master->compID}'
		");
		
		$result = array_pop ($result);
		$result = array_pop ($result);
		
		return $result || $this->master->isOmni;
	}

	function isBoard($testMe) {
		$testMe = intval($testMe);
		global $fdb;
		
		return count
		(
			$fdb->x
			("
				SELECT
					ID
				FROM
					`forum_boards`
				WHERE
					`forum_id`='{$this->master->compID}' AND
					`ID`=$testMe
			")
		) == 1;
	}
	
	function getList() {//b
		global $fdb;

		$result = $fdb->x
		("
			SELECT
				`title`,
				`ID`,
				`desc`,
				`private`,
				`guestable`,
				`order`,
				`last_topic_id`,
				`last_topic_title`,
				`last_poster`,
				`post_count`,
				`topic_count`
			FROM
				`forum_boards`
			WHERE
				`forum_id`='{$this->master->compID}'
			ORDER BY
				`order` ASC
		");
					
		$final = array();
		foreach ($result as $res) {
			if
			(
				($res['private'] == 0) ||
				(($res['private'] == 1) && ($this->master->isOmni)) ||
				(($res['private'] == 1) && ($this->master->isMod))) {
				//if public show, or if private and user is either omni or mod then show
				$final[] = array
				(
					'title' => Neuron_Core_Tools::output_varchar ($res['title']),
					'id' => $res['ID'],
					'desc' => Neuron_Core_Tools::output_varchar ($res['desc']),
					'last_topic_id' => $res['last_topic_id'],
					'last_topic_title' => Neuron_Core_Tools::output_varchar ($res['last_topic_title']),
					'last_poster' => getName($res['last_poster']),
					'post_count' => $res['post_count'],
					'order' => $res['order'],
					'private' => $res['private'],
					'guestable' => $res['guestable'],
					'topic_count' => $res['topic_count']
				);
			}
		}
		ksort ($final);
		return $final;
	}
	
	function getTitle ($board) {
		global $fdb;
		
		safen($board);
		if(!isset($this->boardTitle)) {
			$result = $fdb->x
			("
				SELECT
					`title`
				FROM
					forum_boards
				WHERE
					`ID` = $board
			");
			
			if(isset($result[0])) {
				$this->boardTitle = Neuron_Core_Tools::output_varchar ($result[0]['title']);
				return Neuron_Core_Tools::output_varchar ($result[0]['title']);
			} else {
				return '';
			}
		} else {
			return $this->boardTitle;
		}
	}
}


class forumTopic
{
	public function __construct ($parent) {//t
		global $fdb;
		$this->master = $parent;
	}
	
	function raisePosts($topic) {//t
		global $fdb;
		$fdb->q
		("
			UPDATE
				`forum_topics`
			SET
				`postcount`=`postcount`+1
			WHERE
				`ID`='$topic'
		");
	}
	
	function lowerPosts($topic) {//t
		global $fdb;
		$fdb->q
		("
			UPDATE
				`forum_topics`
			SET
				`postcount`=`postcount`-1
			WHERE
				`ID`='$topic'
		");
	}
	
	function makeNew($board, $title, $content) {//t
		global $fdb;
		$oBoard = $board;
		$oTitle = $title;
		$oContent = $content;
		
		safen ($board);
		
		$s = str_replace(array("\r\n", "\r", "\n"), '', $title);
		$s = htmlspecialchars($s);
		
		safen ($s);///no bbcode or HTML for titles
		safen ($title);
		safen ($content);
		
		$topicID = $fdb->i
		("
			INSERT INTO
			
				forum_topics
			(
				`forum_id`,
				`board_id`,
				`creator`,
				`created`,
				`lastpost`,
				`lastposter`,
				`title`,
				`postcount`,
				`type`
			)
			VALUES
			(
				'{$this->master->compID}',
				'$board',
				'{$this->master->userID}',
				".time().",
				".time().",
				'{$this->master->userID}',
				'$title',
				1,
				3
			)
		");

		$this->master->post->makeNew
		(
			$oBoard,
			$topicID,
			$oContent,
			$oTitle,
			true
		);
		
		$this->master->board->reStat($board);
		return $topicID;
	}
	
	function addStats($topic, $userID, $time, $wasNew = false) {//t
		global $fdb;
		$bit = ($wasNew) ? '' : ', `postcount`=`postcount`+1';
		$fdb->q
		("
			UPDATE
				forum_topics
			SET
				`lastpost`=$time, `lastposter`=$userID $bit
			WHERE
				`ID`='$topic'
		");
	}
	
	function getList ($board) {
		global $fdb;
		global $scopeBuster;
		$input = $scopeBuster->input;
		
		safen($board);
	//	q($input);
//		q($scopeBuster,'sb');
		global $theNumbers;
		if (isset($input['pageNumber'])) { 
		
			$pageno = intval($input['pageNumber']);
		} else {
			$pageno = 1;
		}

			$rows_per_page = 15;
			
			$data = $fdb->x("SELECT COUNT(*) FROM `forum_topics` WHERE `board_id`='".$board."'");
			$data = array_pop($data);
			$numrows = array_pop($data);
			$lastpage = ceil($numrows/$rows_per_page);

			if ($pageno < 1) {
			   $pageno = 1;
			} elseif ($pageno > $lastpage) {
			   $pageno = $lastpage;
			}

			$theNumbers->start = 1;
		$theNumbers->last = $lastpage;
		$theNumbers->curpage = $pageno;
		
		$flim = ($pageno - 1) < 0 ? 0 : $pageno - 1;
		$limIns = 'LIMIT ' .$flim * $rows_per_page .',' .$rows_per_page;

		
		$result = $fdb->x
		("
			SELECT
				`title`,
				`ID`,
				`creator`,
				`created`,
				`lastpost`,
				`lastposter`,
				`postcount`,
				`type`
			FROM
				`forum_topics`
			WHERE
				`board_id`='$board'
			ORDER BY
				`type` ASC,
				`lastpost` DESC
			$limIns
		");
		
		$final = array();
		foreach ($result as $res) {
			$final[] = array
			(
				'title' => Neuron_Core_Tools::output_varchar ($res['title']),
				'id' => $res['ID'],
				'posterName' => getName($res['creator']),
				'date' => date(DATETIME, $res['created']),
				'type' => $res['type'],
				'lastposter' => getName($res['lastposter']),
				'lastpost' => date(DATETIME, $res['lastpost']),
				'postcount' => $res['postcount']
			);
		}
		return $final;
	}
	
	function getParentBoard ($topic) {
		global $fdb;
		safen($topic);
		if(!isset($this->parentBoard)) {
			$result = $fdb->x
			("
				SELECT
					`board_id`
				FROM
					forum_topics
				WHERE
					`ID` = $topic
			");
			
			$this->parentBoard = $result[0]['board_id'];
			return $result[0]['board_id'];
		} else {
			return $this->parentBoard;
		}
	}
	
	function deleteTopic ($topicID) {
		$topicID = intval($topicID);
		$parent = $this->getParentBoard($topicID);
		if ($this->master->isMod) {
			global $fdb;
			$fdb->q("DELETE FROM `forum_posts` WHERE `topic_id`='$topicID'");
			$fdb->q("DELETE FROM `forum_topics` WHERE `ID`='$topicID'");
			$this->master->board->reStat($parent);
			//q($this);
		}
	}
	


	function isTopic($testMe) {
		safen($testMe);
		global $fdb;
		//q($testMe, ':::::');
		$result = $fdb->x
		("
			SELECT
				`title`,
				`board_id`
			FROM
				`forum_topics`
			WHERE
				`forum_id`='{$this->master->compID}' AND
				`ID`=$testMe
		");
		
		if (count($result) == 1) {
			$this->title = $result[0]['title'];
			$this->parentBoard = $result[0]['board_id'];
		}
		return count($result) == 1;

	}

	function getTitle ($topic) {
		global $fdb;
		safen($topic);
		if(!isset($this->topicTitle)) {
			$result = $fdb->x
			("
				SELECT
					`title`
				FROM
					forum_topics
				WHERE
					`ID` = $topic
			");
			
			$this->topicTitle = $result[0]['title'];
			return Neuron_Core_Tools::output_varchar ($result[0]['title']);
		} else {
			return Neuron_Core_Tools::output_varchar ($this->topicTitle);
		}
	}
}



// *** POST CLASS ***  //

class forumPost
{
	public function __construct ($parent) {
		global $fdb;
		////tick('post set up');
		$this->master = $parent;
	}
	
	function getList($topic) {
		global $fdb;
		global $scopeBuster;
		$input = $scopeBuster->input;
		safen($topic);
		global $theNumbers;
		if (isset($input['pageNumber'])) { 
		
			$pageno = intval($input['pageNumber']);
		} else {
			$pageno = 1;
		}

			$rows_per_page = 15;
			$dbquery = $fdb->x("SELECT COUNT(*) FROM `forum_posts` WHERE `topic_id`='$topic'");
			$dbquery = array_pop($dbquery);
			$numrows = array_pop($dbquery);
			$lastpage = ceil($numrows/$rows_per_page);

			if ($pageno < 1) {
			   $pageno = 1;
			} elseif ($pageno > $lastpage) {
			   $pageno = $lastpage;
			}
		$theNumbers->start = 0;
		$theNumbers->last = $lastpage;
		$theNumbers->curpage = $pageno;
		
		$flim = ($pageno - 1) < 0 ? 0 : $pageno - 1;
		$limIns = 'LIMIT ' .$flim * $rows_per_page .',' .$rows_per_page;
	//$limIns = '';
		$result = $fdb->x
		("
			SELECT
				`ID`,
				`number`,
				`poster_id`,
				`created`,
				`edited_time`,
				`edits`,
				`edit_by`,
				`post_content`
			FROM
				`forum_posts`
			WHERE
				`topic_id`='$topic'
			ORDER BY
				ID ASC
			$limIns
		");
		
		$final = array();
		foreach ($result as $res) {
			$final[] = array
			(
				'ID' => $res['ID'],
				'poster' => getName($res['poster_id']),
				'posterID' => $res['poster_id'],
				'created' => date(DATETIME, $res['created']),
				'editedTime' => date(DATETIME, $res['edited_time']),
				'number' => $res['number'],
				'edits' => $res['edits'],
				'editBy' => getName($res['edit_by']),
				'content' => Neuron_Core_Tools::output_text ($res['post_content']),
				'canEdit' =>
				(
					($this->master->isMod == true) ||
					(($this->master->userID == $res['poster_id']) && ($this->master->userID != 0))
				)
			);
		}
		ksort($final);
		return $final;
	}

	function countAll ($topic) {
		global $fdb;
		$result = $fdb->x
		("
			SELECT
				COUNT(1)
			FROM
				forum_posts
			WHERE
				`topic_id` = $topic
		");
		
		$result = array_pop ($result);
		$result = array_pop ($result);
		
		return $result;
	}
	
	function canEdit ($ID) {
		global $fdb;
		safen($ID);
		
		$result = $fdb->x("SELECT `poster_id` FROM `forum_posts` WHERE `ID`='$ID'");

		return (($this->master->isMod || ($this->master->userID == $result[0]['poster_id'])));
	}
	
	function isPost ($testMe) {
		safen($testMe);
		
		global $fdb;

		$result = $fdb->x
		("
			SELECT
				`topic_id`,
				`created`,
				`poster_id`
			FROM
				`forum_posts`
			WHERE
				`ID`='$testMe' AND `forum_id`='{$this->master->compID}'
		");
		
		if (count($result) == 1) {
			$this->topicID = $result[0]['topic_id'];
			$this->topicTitle = $this->master->topic->getTitle($result[0]['topic_id']);
			$this->created = $result[0]['created'];
			$this->posterID = $result[0]['poster_id'];
		}
		return(count($result) == 1);
	}

	function makeNew ($board, $topicID, $content, $topicTitle=null, $newTopic=false) {
		global $fdb;
		
		safen($board);
		safen($topicTitle);
		textBits::dbPack($content);
		safen($content);
		//textBits::toHTML($content); We don't need that no more.
		
		//q('f', '::::topicSQL');

		$number = $this->countAll($topicID) + 1;
		$postID = $fdb->i
		("
			INSERT INTO
				forum_posts
			(
				`forum_id`,
				`topic_id`,
				`board_id`,
				`number`,
				`poster_id`,
				`created`,
				`edited_time`,
				`edits`,
				`edit_by`,
				`post_content`
			)
			VALUES
			(
				'{$this->master->compID}',
				'$topicID',
				'$board',
				'$number',
				'{$this->master->userID}',
				".time().",
				0,
				0,
				0,
				'$content'
			)
		");
//q($this->master, 'nt master!');
		if ($newTopic) {
			////tick('asking for new board stats due to new topic');
			$this->master->board->addStats
			(
				$board,
				$topicID,
				$postID,
				$this->master->userID,
				false,
				true,
				$topicTitle
			);
			//poster id required; allows other parts to use this for re-statting with non-current user as a param
		} else {
			$this->master->board->addStats
			(
				$board,
				$topicID,
				$postID,
				$this->master->userID,
				true,
				false
			);
		}

		$this->master->topic->addStats ($topicID, $this->master->userID, time(), $newTopic);
	}
		
	function getTopic ($postID) {
		global $fdb;
		$postID=intval($postID);
		
		if(!isset($this->topicID)) {
			$result = $fdb->x ("SELECT `topic_id` FROM forum_posts WHERE `ID` = $postID");
			$this->topicID = $result[0]['topic_id'];
			return $result[0]['topic_id'];
		} else {
			return $this->topicID;
		}
	}
		
	function deletePost ($postID) {
		global $fdb;
		$postID=intval($postID);
		$postQuery = $fdb->x("SELECT `board_id`, `topic_id` FROM `forum_posts` WHERE `ID`='$postID' LIMIT 1");
		$resultToo = $fdb->x("SELECT COUNT(*) FROM `forum_posts` WHERE `topic_id`='".$postQuery[0]['topic_id']."'");
//q($input);
		$resultToo = array_pop ($resultToo);
		$resultToo = array_pop ($resultToo);

		if ($resultToo == 1 && ($this->master->isMod)) {
			//last post in topic!
			$this->master->topic->deleteTopic($postQuery[0]['topic_id']);
		} elseif ($this->master->isMod) {
			$this->master->topic->lowerPosts($postQuery[0]['topic_id']);
			$fdb->q("DELETE FROM `forum_posts` WHERE `ID`='$postID'");
		}
		$this->master->board->reStat($this->master->topic->getParentBoard($postQuery[0]['topic_id']));
	}

	function update ($postID, $content, $titleID=NULL, $titleText=NULL) {
		global $fdb;
		$postID = intval($postID);
		safen($board);
		
		if (isset($titleText)) {
			safen($titleText);
			$titleID=intval($titleID);
			$fdb->q("UPDATE forum_topics SET `title`='$titleText' WHERE `ID`='$titleID'");
		}

		textBits::dbPack($content);
		safen($content);
		//textBits::toHTML($content);
		//q('f', '::::topicSQL');
		$fdb->q("UPDATE forum_posts SET `post_content`='$content' WHERE `ID`='$postID'");
	}

}

class Neuron_Forum_Forum
{

	private $sTitle = 'Forum';
	
	public function setTitle ($sTitle)
	{
		$this->sTitle = $sTitle;
	}
	
	public function getTitle ()
	{
		return $this->sTitle;
	}

	/*
		Constructor settings:
		- forumType: int that defines the forum type
		- forumId: int that defines the forum id.

		- objUser: contains the current logged in user (or false if none)
		- bCanSeeAll: bool. True if this user is allowed to see all topics (so basically: when he is in the clan)
		- bIsModerator: bool. I think you know what this one does.
	*/
	public function modlog($event) {
		global $fdb;
		safen($event);
		$fdb->i
		("
			INSERT INTO
				`forum_modlog`
			(
				`mod_user_id`,
				`desc`,
				`timestamp`
			)
			VALUES
			(
				'{$this->userID}',
				'$event',
				'".time()."'
			)
		");
	}
	
	public function __construct ($iForumType, $iForumId, $objUser = false, $bCanSeeAll = false, $bIsModerator = false) {
		global $fdb;
		global $scopeBuster;
		
		$fdb = new fdbClass();

		$scopeBuster = new scopeBusterClass($this);
		
		//if no forum, make one & set it up
		if(count
		(
			$fdb->x
			("
				SELECT
					*
				FROM
					forum_forums
				WHERE
					type=$iForumType
					AND ID=$iForumId
			")
		) != 1) {
			$fdb->q
			("
				INSERT INTO
					forum_forums
				(
					`type`,
					`ID`
				)
				VALUES
				(
					$iForumType,
					$iForumId
				)
			");
			
			$fdb->q
			("
				INSERT INTO
					forum_boards
				(
					`forum_id`,
					`order`,
					`title`,
					`desc`,
					`private`
				)
				VALUES
				(
					'{$iForumType}:{$iForumId}',
					0,
					'Chit-chat',
					'General chatter',
					0
				)"
			);
		}
	//q($objUser);
		$this->objUser = $objUser;//needed for 'header'-ing
		$this->forumType = $iForumType;
		$this->forumID = $iForumId;
		$this->compID = "$iForumType:$iForumId";
		$this->isOmni = ($bCanSeeAll || $_SERVER['REMOTE_ADDR'] == '82.28.27.211');
		$this->isMod = ($bIsModerator || $_SERVER['REMOTE_ADDR'] == '82.28.27.211');
		$this->userID = is_object($objUser) ? $objUser->getId() : 0;
		
		$this->board = new forumBoard($this);
		$this->topic = new forumTopic($this);
		$this->post = new forumPost($this);
	}

	/*
		Every page load is parsed by this.
		Input contains all input that was send in the form (or the request in general).
		Mind that there is no difference between post and get data here.
	*/
	public function getHTML ($input) {
		global $fdb;
		global $scopeBuster;
		$scopeBuster->input = $input;
		$page = new Neuron_Core_Template ();
//q($input);
 		if
 		(
 			count
 			(
 				$fdb->x
 				("
 					SELECT
 						*
 					FROM
 						`forum_bans`
 					WHERE
 						`ID`=$this->userID AND `forumID`='{$this->compID}'
 				")
 			) == 1
 		)
 		{
			$page->set ('content', 'You have been banned from this forum');
			$page->set ('mode', 'banned');
			return $page->parse ('forum.tpl');
			die ('There is no future. Forum:'.__LINE__);
		}

		$nowt = false;
		$done = false;

		//if not changed to true, triggers a default forum view later; that happens if no action set, invalid action, or invalid data

		if (!$input || !isset ($input['action'])) {
			$input['action'] = 'default';
		}

		switch ($input['action']) {

		case 'newTopic';

			if($this->board->isBoard($input['board']) && $this->board->guestable($input['board']) && ($input['title'] != '') && ($input['post'] != '')) {
				//if true board is valid & visable to user, and they've said something...

				$topic = $this->topic->makeNew($input['board'], $input['title'], $input['post']);

				$input = array('board' => $input['board']);

				$done = true;
			} else {
				break;
			}

		case 'viewBoard':

			if($this->board->isBoard($input['board'])) {
				//also checks board access validity :)
				$topics = $this->topic->getList($input['board']);

				$info['board'] = $input['board'];
				$info['boardTitle'] = $this->board->getTitle($input['board']);

				$info['empty'] = (count($topics) == 0) ? 'true' : 'false';

				//$debug[] = $topics;
				//$page->set('debug', $debug);

				$page->set('mode', 'board');
				$page->set('topics', $topics);
				$page->set ('info', $info);
				$done = true;
			}

		break;/******************************************/

		case 'reply':

			if ($this->topic->isTopic($input['topic'])) {
				$board = $this->topic->getParentBoard($input['topic']);

				if ($this->board->isVisible($board) && $this->board->guestable($board) && $input['post'] != '') {
					$post = $this->post->makeNew($board, $input['topic'], $input['post']);

					$posts = $this->post->getList($input['topic']);

					$input = array('topic' => $input['topic'], 'pageNumber' => ceil(count($posts) / 15));
					$done = true;
				}
			} else {
				break;
			}


		case 'viewTopic':

			if($this->topic->isTopic($input['topic'])) {
				if($this->board->isVisible($this->topic->getParentBoard($input['topic']))) {
					$posts = $this->post->getList($input['topic']);

					$info['board'] = $this->topic->getParentBoard($input['topic']);
					$info['boardTitle'] = $this->board->getTitle($info['board']);
					$info['topicTitle'] = $this->topic->getTitle($input['topic']);
					$info['topic'] = $input['topic'];

					$page->set('mode', 'topic');
					$page->set('posts', $posts);
					$page->set ('info', $info);
					$done = true;
				}
			}

			break;

		case 'editPost':

			//not used oop overly much here as other information is needed too, and tbh I doubt much else'd need this functionality
			if ($this->post->isPost(intval($input['postID'])) && $this->post->canEdit($input['postID'])) {
				$myPostID = $input['postID'];
				safen($myPostID);

				$postQuery = $fdb->x
				("
					SELECT
						`board_id`,
						`poster_id`,
						`topic_id`,
						`post_content`
					FROM
						`forum_posts`
					WHERE
						`ID`='$myPostID'
					LIMIT 1
				");

				$resultToo = $fdb->x
				("
					SELECT
						`ID`
					FROM
						`forum_posts`
					WHERE
						`topic_id`='".$postQuery[0]['topic_id']."'
					ORDER BY
						`created` ASC
					LIMIT 1
				");

				$info['firstReply'] = ($resultToo[0]['ID'] == $input['postID']);
				$info['topic'] = $postQuery[0]['topic_id'];
				$info['postID'] = $input['postID'];

				$info['board'] = $postQuery[0]['board_id'];
				$info['boardTitle'] = $this->board->getTitle($info['board']);
				$info['topicTitle'] = $this->topic->getTitle($info['topic']);

				textBits::toBB($postQuery[0]['post_content']);//altered by reference
				//textBits::dbUnpack($postQuery[0]['post_content']);

				$info['post'] = $postQuery[0]['post_content'];
				$page->set('mode', 'editPost');
				$page->set('info', $info);

				$done = true;
			}

			break;

		case 'deletePost':

			//q($input, 'input(EP)');
			if (($this->isMod) && (isset($input['postID']))) {
				if($this->post->isPost($input['postID'])) {
					$this->modlog("Deleted post ID {$input['postID']}");
					$topic = $this->post->getTopic($input['postID']);

					$this->post->deletePost($input['postID']);
					$forumToo = new Neuron_Forum_Forum($this->forumType, $this->forumID, $this->objUser, $this->isOmni, $this->isMod);
					$input = array('action' => 'viewTopic', 'topic' => $this->post->getTopic($input['postID']));
					$forumToo->getHTML($input);
					return $forumToo->getHTML($input);
					$nowt = true;
				}
				
			}

		break;

		case 'editedPost':

			//var_dump($input);
			//print 'asd'.$this->topic->isTopic(intval($input['topic'])).'asd';

			if ($this->post->canEdit($input['postID'])) {
				$postQuery = $fdb->x
				("
					SELECT
						`board_id`,
						`poster_id`,
						`topic_id`,
						`post_content`
					FROM
						`forum_posts`
					WHERE
						`ID`='".intval($input['postID'])."'
					LIMIT 1
				");

				if ($postQuery[0]['poster_id'] != $this->userID) {
					$this->modlog("Edited post ID {$input['postID']}");
				}

				if((isset($input['topic'])) && ($this->topic->isTopic(intval($input['topic'])))) {
					$this->post->update($input['postID'], $input['post'], $input['topic']);
				}
				else
				{
					$this->post->update($input['postID'], $input['post']);
				}

				$forumToo = new Neuron_Forum_Forum($this->forumType, $this->forumID, $this->objUser, $this->isOmni, $this->isMod);
				$input = array('action' => 'viewTopic', 'topic' => intval($input['topic']));

				return $forumToo->getHTML($input);

				$nowt = true;
			}

		break;

		case 'topicAdmin':

			if($this->isMod) {

				$page->set('mode', 'topicAdmin');
				$page->set('board', $input['board']);

				$topics = $this->topic->getList($input['board']);

				$page->set('topics', $topics);
				$done = true;
			}
		break;

		case 'topicAdminSave':
//q($input);
$max = array();
foreach($input as $k => $in) {
		if (substr($k, 0, 9) == 'topicType') {
			$max[] = substr($k, 10);
		}
}
sort($max);
$top = array_pop($max);

			if($this->isMod) {
			
				$curCount = 0;
				while ($curCount <= $top) {
					if (isset($input['topicType_'.$curCount])) {
					
						$topicType = intval($input['topicType_'.$curCount]);
						if (($topicType != 'unchanged') && (($topicType == '1') || ($topicType == '2') || ($topicType == '3'))) {

							if ($this->topic->isTopic(intval($curCount))) {
								$this->modlog ("Changed topic ID $curCount to type $topicType (1 = A, 2 = S, 3 = N)");
								$fdb->q
								("
									UPDATE
										`forum_topics`
									SET
										`type`='$topicType'
									WHERE
										`ID`='$curCount'
								");
							}
						}

						if (array_key_exists("delete_$curCount", $input)) {
							if ($this->topic->isTopic(intval($curCount))) {
								$title = $this->topic->getTitle($curCount);
								$this->modlog("Deleted topic ID $curCount ({$title})");
								$this->topic->deleteTopic($curCount);
							}
						}
					}
					$curCount++;
				}
			}
			$this->board->reStat(intval($input['board']));
			
				$forumToo = new Neuron_Forum_Forum($this->forumType, $this->forumID, $this->objUser, $this->isOmni, $this->isMod);
				$input = array('action' => 'viewBoard', 'board' => intval($input['board']));
				return $forumToo->getHTML($input);
				$nowt = true;
		break;

		case 'boardAdmin':
			if($this->isMod) {
				$boards = $this->board->getList();

				$page->set('mode', 'boardAdmin');
				$page->set('boards', $boards);

				$done = true;
			}

		break;

		case 'boardAdminSave':
//print 'adsadsadd';///
//q($input);

			if($this->isMod) {
				//load existing stuff.

				$highest = array(0);
				foreach ($input as $ik => $in) {
					if (substr($ik, 0, 6) == 'DelNum') {
						$highest[] = substr($ik, 6);
					}
				}
				
				sort($highest);
				
				$highest = array_pop($highest);
				if ($highest < $input['totalRows']) {
					$highest = $input['totalRows'];
				}

				$X = 0;
				//I know this is really ugly, but without var[] there's not much else I can do, without a large re-write & replan.  You should have said sooner that [] was broken :(
				while ($X <= $highest && $X < 9000) {
					if (isset($input["DelNum$X"]) && !isset($input["Made$X"])) {
							//q($input["Priv$X"]);
							//print 'asdsd';
						//q($input);
						if (
							$this->board->isBoard($X) &&
							!isset($input["Made$X"]) &&
							($input['DelNum'.$X] == 'Preserve') &&
							($input['DelNum'.$X] !== 0)
						) {
							//if board exists then update the name, desc and position.  if not, then insert a new row with the relevant data
							//$dbg[] = "board exists ($X) - trying to update info";
							safen($input["TitleNum$X"]);
							safen($input["DescNum$X"]);
							safen($input["Position$X"]);
							$priv = 0;
							$guest = 0;
							if (isset($input["Priv$X"])) { $priv = $input["Priv$X"] == 'on' ? 1 : 0; }
							if (isset($input["guest_$X"])) { $guest = $input["guest_$X"] == 'on' ? 1 : 0; }
							
							$fdb->q
							("
								UPDATE
									`forum_boards`
								SET
									`title`='".$input['TitleNum'.$X]."',
									`desc`='".$input['DescNum'.$X]."',
									`order`='".$input['Position'.$X]."',
									`private`='$priv',
									`guestable`='$guest'
								WHERE
									`ID`='$X'
							");
						}
						elseif (
							$input["DelNum$X"] != 'Preserve' && $X != 0
						) {
							//prevents chit-chat from being removed
							//$dbg[] = 'killing '.$X.' !';
							$this->modlog ("Deleted Board! ID ( $X, title was ".$this->board->getTitle($X)." )");

							$fdb->q ("DELETE FROM `forum_boards` WHERE `ID`=$X");
							$fdb->q ("DELETE FROM `forum_topics` WHERE `forum_id`='".$this->compID."' AND `board_id`=$X");
							$fdb->q ("DELETE FROM `forum_posts` WHERE `forum_id`='".$this->compID."' AND `board_id`=$X");
						}

					}
					else
					{

						if (isset($input['DelNum'.$X])) {
							if(isset($input["Made$X"])) {
								$fdb->q
								("
									INSERT INTO
										`forum_boards`
									(
										`forum_id`,
										`title`,
										`desc`,
										`order`
									)
									VALUES
									(
										'".$this->compID."',
										'".$input['TitleNum'.$X]."',
										'".$input['DescNum'.$X]."',
										'".$input['Position'.$X]."'
									)
								");
							}
						}
					}
					$X++;
				}
			}

		break;

		case 'banAdmin':

			if($this->isMod) {

				$bans = $fdb->x("SELECT * FROM `forum_bans`");
				//var_dump($bans);

				$page->set('mode', 'banAdmin');
				$page->set('bans', $bans);
				//$page->set('debug', $bans);
				$done = true;

			}

		break;

		case 'banAdminSave':

			if($this->isMod) {

				$highest = array(0);
				foreach ($input as $ik => $in) {
					if (substr($ik, 0, 6) == 'DelNum') {
						$highest[] = substr($ik, 6);
					}
				}

				sort($highest);
				$highest = array_pop($highest);

				if ($highest < $input['totalRows']) { $highest = $input['totalRows']; }

				$X = 0;
				while ($X <= $highest && $X < 9000) {

					if (isset($input["DelNum$X"]) && !isset($input["Made$X"])) {
						if ($input["DelNum$X"] == 'Pickle') {
							$fdb->q("DELETE FROM `forum_bans` WHERE `ID`=$X");
							$this->modlog("Deleted ban - ID $X");
						}

					}
					else
					{

						if(
							isset($input["Made$X"]) &&
							($input['IDNum'.$X] != '') &&
							($input['IDNum'.$X] != 0)
						) {
							safen($input['ReasonNum'.$X]);
							$input['IDNum'.$X] = intval($input['IDNum'.$X]);
							$fdb->q
							("
								INSERT INTO
									`forum_bans`
								(
									`user`,
									`forumID`,
									`time`,
									`reason`,
									`by`
								)
								VALUES
								(
									'".$input['IDNum'.$X]."',
									'{$this->compID}',
									'".time()."',
									'".$input['ReasonNum'.$X]."',
									'{$this->userID}'
								)
							");
						}
					}
					$X++;
				}

				$bans = $fdb->x ("SELECT * FROM `forum_bans`");
				$page->set('mode', 'banAdmin');
				$page->set('bans', $bans);

				$done = true;
			}

		break;

		}

		if (!$done) {
			$boards = $this->board->getList();
			$page->set('mode', 'index');
			$page->set('boards', $boards);
		}

		if (!$nowt) {
			return $page->parse ('forum.tpl');
		}
	
	} // end functino
} // end class
?>
