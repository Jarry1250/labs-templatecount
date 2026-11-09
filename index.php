<?php
	/*
	Template transclusion counter © 2011, 214 Harry Burt <jarry1250@gmail.com>

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 2 of the License, or
	( at your option ) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA.
	*/

	ini_set( 'display_errors', 1 );
	error_reporting( E_ALL );
	require_once( '/data/project/jarry-common/public_html/global.php' );
	$I18N->setDomain( 'templatecount' );
	$oldtime = time();

	list( $interfaceLang, ) = explode( '-', $I18N->getLang() );
	$language = ( isset( $_GET['lang'] ) && $_GET["lang"] != "" ) ? htmlspecialchars( $_GET['lang'] ) : $interfaceLang;
	$namespace = ( isset( $_GET['namespace'] ) && $_GET["namespace"] != "" ) ? htmlspecialchars( $_GET['namespace'] ) : 10; //10 is template namespace
	$templateName = ( isset( $_GET['name'] ) && $_GET["name"] != "" ) ? str_replace( "_", " ", htmlspecialchars( $_GET['name'], ENT_QUOTES ) ) : '';

	/**
	 * The public wikis, as an array of database name => site URL. Private wikis are left out because they are not on the replicas.
	 */
	function getWikis() {
		$http = HTTP::getDefaultInstance();
		$json = json_decode( $http->get( 'https://meta.wikimedia.org/w/api.php?format=json&action=sitematrix&smsiteprop=dbname%7Curl&smlangprop=site' ), true );

		$wikis = array();
		if( !isset( $json['sitematrix'] ) ) return $wikis;

		foreach( $json['sitematrix'] as $key => $group ){
			if( $key === 'count' ) continue;
			$sites = ( $key === 'specials' ) ? $group : $group['site'];
			foreach( $sites as $site ){
				if( !isset( $site['private'] ) ) {
					$wikis[$site['dbname']] = $site['url'];
				}
			}
		}
		return $wikis;
	}

	/**
	 * Accepts a database name (enwikisource) as well as the older short form (en, commons, zh-yue). Returns null if there is no such wiki.
	 */
	function resolveWiki( $input, $wikis ) {
		$dbname = str_replace( '-', '_', $input );
		foreach( array( $dbname, $dbname . 'wiki' ) as $candidate ){
			if( isset( $wikis[$candidate] ) ) return $candidate;
		}
		return null;
	}

	/**
	 * getNamespaceSelect() builds its API URL from a subdomain and a project, so split the host into those.
	 */
	function getWikiNamespaceSelect( $url, $default ) {
		$labels = explode( '.', parse_url( $url, PHP_URL_HOST ) );
		if( count( $labels ) === 2 ) array_unshift( $labels, 'www' ); // wikisource.org
		return getNamespaceSelect( $labels[0], $default, $labels[1] );
	}

	function connectToWiki( $dbname ) {
		// Commons' links tables moved to the x4 cluster in September 2026 and were dropped from s4 on the replicas.
		// See https://wikitech.wikimedia.org/wiki/News/2026_Commons_links_tables_database_split
		$prefix = in_array( $dbname, array( 'commonswiki', 'testcommonswiki' ), true ) ? 'links.' : '';
		// Not dbconnect(): it uses the deprecated *.labsdb aliases, which have had no new wikis added since 2018.
		$mycnf = parse_ini_file( '/data/project/templatecount/replica.my.cnf' );
		$db = new mysqli( $prefix . $dbname . '.web.db.svc.wikimedia.cloud', $mycnf['user'], $mycnf['password'], $dbname . '_p' );
		unset( $mycnf );
		return $db;
	}

	echo get_html( 'header', 'Template transclusion count' );

	if( !preg_match( "/^[a-z0-9_-]{2,40}$/", $language ) ) { // Safety precaution
		echo '<b>Error:</b> Language parameter with invalid format.<br>';
		die( get_html( 'footer' ) );
	}
	if( !is_numeric( $namespace ) ) { // Safety precaution
		echo '<b>Error:</b> Namespace parameter must be numerical.<br>';
		die( get_html( 'footer' ) );
	}

	$wikis = getWikis();
	if( count( $wikis ) === 0 ) {
		echo '<b>Error:</b> Could not load the list of wikis. Please try again later.<br>';
		die( get_html( 'footer' ) );
	}
	$dbname = resolveWiki( $language, $wikis );
	if( $dbname === null ) {
		if( isset( $_GET['lang'] ) && $_GET["lang"] != "" ) {
			echo '<b>Error:</b> No public wiki found with that name.<br>';
			die( get_html( 'footer' ) );
		}
		// $language only came from the interface language, which need not have a wiki of its own.
		$language = 'en';
		$dbname = 'enwiki';
	}
?>
		<h3><?php echo _html( 'enter-details' ); ?></h3>
		<p><?php echo _html( 'introduction' ); ?></p>

		<form action="index.php" method="GET">
			<p><label for="lang"><?php echo _html( 'language-label' ) . _g( 'colon-separator' );?>&nbsp;</label><input type="text" name="lang" id="lang" value="<?php echo $language; ?>" style="width:160px;" maxlength="40" required="required"> <small>e.g. en, commons, enwikisource</small><br />
			<label for="namespace"><?php echo _html( 'namespace-label' ) . _g( 'colon-separator' );?>&nbsp;</label><?php echo getWikiNamespaceSelect( $wikis[$dbname], $namespace ); ?><br />
			<label for="name"><?php echo _html( 'pagename-label' ) . _g( 'colon-separator' );?>&nbsp;</label><input type="text" name="name" id="name" style="width:200px;" value="<?php echo $templateName; ?>" required="required"/>
			<input type="submit" value="<?php echo _g( 'form-submit'); ?>" /></p>
		</form>
		<?php
			if( isset( $_GET['lang'] ) && $templateName != '' ){
				Counter::increment( 'templatecount/sincejune2011.txt' );

				$templateName = str_replace( "Template:", "", $templateName );
				$templateName = mb_strtoupper( mb_substr( $templateName, 0, 1 ) ) . mb_substr( $templateName, 1 ); // For Xeno
				$templateName = str_replace( " ", "_", $templateName );
				// echo "<!-- Actually checking database for query '" . htmlspecialchars( $db->real_escape_string( $templateName ) ) . "' -->\n";
				$db = connectToWiki( $dbname );
				$result = $db->query( "SELECT count(*) FROM templatelinks INNER JOIN linktarget ON tl_target_id = lt_id WHERE lt_title = '" .  $db->real_escape_string( $templateName ) ."' AND lt_namespace = " . $db->real_escape_string( $namespace ) . ";" );
				$row = $result->fetch_array();
				$count = $row[0];

				echo "<h3>" . _html( 'transclusion-count-label' ) . "</h3>\n";
				$result = "<p>" . _html( 'transclusion-count', array( 'variables' => array( $count ) ) );
				if ( $count === 0 ) {
					$result .= " " . _html( 'error-suggestion' );
				}
				echo $result . "</p>\n";
				$diff = time() - $oldtime;
				echo "<p style=\"font-size:60%;\">" . _html( 'time-label' ) . _g( 'colon-separator' ) . " $diff " . _g( 'seconds', array( 'variables' => array( $diff ) ) ) . ".</p>";
			}
		?>
		<a name="bottom" id="bottom"></a>
		<script type="text/javascript">
			<?php
				if( isset( $_GET['lang'] ) && $_GET["lang"] != "" && $templateName != '' ){
					echo "document.location='#bottom';\n";
				}
			?>
			$( document ).ready( function(){ $( "#translateform" ).html5form( { async:false } ); } );
		</script>
		<?php echo '<!-- Used ' . Counter::getCounter( 'templatecount/sincejune2011.txt' ) . " times since early June 2011. -->"; ?>
<?php
	echo get_html( 'footer' );
