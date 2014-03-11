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
	require_once( '/data/project/jarry-common/public_html/database.php' );
	$I18N->setDomain( 'templatecount' );
	$oldtime = time();

	$interfaceLang = $I18N->getLang();
	if( !preg_match( "/^[a-z]{2,3}$/", $interfaceLang ) ) die(); //Safety precaution
	$language = ( isset( $_GET['lang'] ) && $_GET["lang"] != "" ) ? htmlspecialchars( $_GET['lang'] ) : $interfaceLang;
	$namespace = ( isset( $_GET['namespace'] ) && $_GET["namespace"] != "" ) ? htmlspecialchars( $_GET['namespace'] ) : 10; //10 is template namespace
	$templateName = ( isset( $_GET['name'] ) && $_GET["name"] != "" ) ? str_replace( "_", " ", htmlspecialchars( $_GET['name'], ENT_QUOTES ) ) : '';

	if( !preg_match( "/^[a-z-]{2,7}$/", $language ) ) die(); // Safety precaution
	if( !is_numeric( $namespace ) ) die(); // Safety precaution
	echo get_html( 'header', 'Template transclusion count' );
?>
		<h3><?php echo _html( 'enter-details' ); ?></h3>
		<p><?php echo _html( 'introduction' ); ?></p>

		<form action="index.php" method="GET">
			<p><label for="lang"><?php echo _html( 'language-label' ) . _g( 'colon-separator' );?>&nbsp;</label><input type="text" name="lang" id="lang" value="<?php echo $language; ?>" style="width:80px;" maxlength="7" required="required">.wikipedia.org<br />
			<label for="namespace"><?php echo _html( 'namespace-label' ) . _g( 'colon-separator' );?>&nbsp;</label><?php echo getNamespaceSelect( $interfaceLang, $namespace ); ?><br />
			<label for="name"><?php echo _html( 'pagename-label' ) . _g( 'colon-separator' );?>&nbsp;</label><input type="text" name="name" id="name" style="width:200px;" value="<?php echo $templateName; ?>" required="required"/>
			<input type="submit" value="<?php echo _g( 'form-submit'); ?>" /></p>
		</form>
		<?php
			if( isset( $_GET['lang'] ) ){
				Counter::increment( 'templatecount/sincejune2011.txt' );

				$templateName = str_replace( "Template:", "", $_GET["name"] );
				$templateName = mb_strtoupper( mb_substr( $templateName, 0, 1 ) ) . mb_substr( $templateName, 1 ); // For Xeno
				$templateName = str_replace( " ", "_", $templateName );
				// echo "<!-- Actually checking database for query '" . htmlspecialchars( $db->real_escape_string( $templateName ) ) . "' -->\n";
				$db = dbconnect( $language . 'wiki-p' );
				$result = $db->query( "SELECT count(*) FROM templatelinks WHERE tl_title = '" .  $db->real_escape_string( $templateName ) ."' AND tl_namespace = " . $db->real_escape_string( $namespace ) . ";" );
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
				if( isset( $_GET['lang'] ) && $_GET["lang"] != "" ){
					echo "document.location='#bottom';\n";
				}
			?>
			$( document ).ready( function(){ $( "#translateform" ).html5form( { async:false } ); } );
		</script>
		<?php echo '<!-- Used ' . Counter::getCounter( 'templatecount/sincejune2011.txt' ) . " times since early June 2011. -->"; ?>
<?php
	echo get_html( 'footer' );