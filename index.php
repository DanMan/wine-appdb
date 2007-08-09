<?php
/**
 * Application database index page.
 *
 * TODO:
 *  - rename outputTopXRowAppsFromRating according to our coding standards
 *  - rename variables that don't follow our coding standards
 */

// application environment
require("path.php");
require(BASE."include/incl.php");

apidb_header("Wine Application Database");
?>
    <img style="float:right;" src="images/appdb_montage.jpg" width=391 height=266 alt="Wine AppDB">

<div class='default_container'>    
<h1>Welcome</h1>

<p>This is the Wine Application Database (AppDB). Here you can get information on application
compatibility with Wine.</p>
<?php
$str_benefits="
    <ul>
        <li>Ability to <a href=\"".BASE."help/?sTopic=voting\" title=\"help on voting\" style=\"cursor: help\">vote</a> on your favorite applications</li>
        <li>Ability to customize the layout and behavior of the AppDB and comments system</li>
        <li>Take credit for your witty posts</li>
        <li>Ability to sign up to be an <a href=\"".BASE."help/?sTopic=maintainer_guidelines\"
            title=\"information about application maintainers\"  style=\"cursor: help\">application maintainer</a>.</li>
        <li>Submit new applications and versions.</li>
        <li>Submit new screenshots.</li>
    </ul>
";
if(!$_SESSION['current']->isLoggedIn()) 
{
    echo "
    <p>Most of the features of the Application Database require that you have a user account and
    are logged in. Some of the benefits of membership are:<p>

    $str_benefits

    <p>So what are you waiting for, [<a href=\"".login_url()."\">log in</a>]
    or [<a href=\"account.php?sCmd=new\">register</a>] now! Your help in
    stomping out Wine issues will be greatly appreciated.</p>";
} else 
{
    echo "
    <p>As an Application Database member you enjoy some exclusive benefits like:<p>

    $str_benefits

    <p>We&#8217;d like to thank you for being a member and being logged in to the system. Your help in
    stomping out Wine issues will be greatly appreciated.</p>";

}

echo "<div style='clear:both; border: 2px solid; background-color: #d7d7d7; font-weight: bold; padding:4px;'>";
echo "<p style='font-size:large;'>Do you use Wine regularly? Interested in helping other users and".
     " keeping information about your favorite applications up-to-date?</p>";
echo "<p style='font-size: medium;'>Consider".
     " becoming an <a href=\"".BASE."help/?sTopic=maintainer_guidelines\"".
     " title=\"information about application maintainers\"  style=\"cursor: help\">application maintainer</a>".
     " for the applications that you use under Wine.</p>";
echo "<p style='font-size:medium;'>As an application maintainer you'll get to post HOWTOs to help other users".
     " run the application. You'll be able to review and process user submitted screenshots".
     " and test results.</p>";
echo "<p style='font-size:medium;'>Dozens of new test results and screenshots are submitted each day and we can".
     " use your help to keep user contributed information reviewed and processed in a timely fashion.".
     " Please consider becoming an application maintainer.";
echo "<p style='font-size:medium;'>- The Appdb Admins<br/> <a href=\"mailto:".APPDB_OWNER_EMAIL."\">".APPDB_OWNER_EMAIL."</a></p>";

if(!$_SESSION['current']->isLoggedIn())
{
  echo "<p style='font-size: medium;'>To become a maintainer, [<a href=\"".login_url()."\">log in</a>]".
    " or [<a href=\"account.php?sCmd=new\">register</a>] and on the application or version page".
    ", depending on whether you want to maintain all versions of an application or a specific version,".
    " click on the <i>'Become a super maintainer of this app'</i> or <i>'Be a maintainer for this version'</i>".
    " button. We'll process your request within a day or so.</p>";
}

echo "</div>";

?>
<?php

    $iNumApps = version::objectGetEntriesCount(false, false);

    $voteQuery = "SELECT appVotes.versionId, count(userId) as count ".
        "FROM appVotes ".
        "GROUP BY versionId ORDER BY count DESC LIMIT 1";
    $hResult = query_parameters($voteQuery);
    $oRow = query_fetch_object($hResult);

    echo "There are <b>$iNumApps</b> applications currently in the database";

    // don't mention the top application if there are no votes yet
    if( !empty($oRow) )
    {
        if($oRow->versionId)
        {
            $shVoteAppLink = version::fullNameLink($oRow->versionId);
            echo " with $shVoteAppLink being the\n";
            echo "top <a href='votestats.php'>voted</a> application.\n";
        } else
        {
            echo " please <a href=\"".BASE."help/?sTopic=voting\" title=\"help on voting\"".
                "style=\"cursor: help\">vote</a> for your favourite application.\n";
        }
    }
?>

<br /><br />

<div class="topx_style platinum">
  <div class="rating_header">
    <div class="rating_title">
      Top-10 <a href="browse_by_rating.php?sRating=Platinum">Platinum</a> List
    </div>
    Applications which install and run flawlessly on an out-of-the-box Wine installation
  </div>
  <div>
    <table class="platinum">
      <tr class="rowtitle">
        <th>Application</th><th>Description</th><th>Screenshot</th>
      </tr>
      <?php
      outputTopXRowAppsFromRating('Platinum', 10);
      ?>
    </table>
  </div>
</div>
<br />

<div class="topx_style gold">
  <div class="rating_header">
    <div class="rating_title">
      Top-10 <a href="browse_by_rating.php?sRating=Gold">Gold</a> List
    </div>
    Applications that work flawlessly with some special configuration
  </div>
  <div>
    <table class="gold">
      <tr class="rowtitle">
        <th>Application</th><th>Description</th><th>Screenshot</th>
      </tr>
      <?php
      outputTopXRowAppsFromRating('Gold', 10);
      ?>
    </table>
  </div>
</div>
<br />

<div class="topx_style silver">
  <div class="rating_header">
    <div class="rating_title">
      Top-10 <a href="browse_by_rating.php?sRating=Silver">Silver</a> List
    </div>
    Applications with minor issues that do not affect typical usage
  </div>
  <div>
    <table class="silver">
      <tr class="rowtitle">
        <th>Application</th><th>Description</th><th>Screenshot</th>
      </tr>
      <?php
      outputTopXRowAppsFromRating('Silver', 10);
      ?>
    </table>
  </div>
</div>

<br /><br />

<h2>Other Wine Application Compatibility Sites</h2>
<p>
<a href="http://frankscorner.org"><b>Frank's Corner</b></a>:  Frank has a fantastic Wine
application site, with tips and how-to's on getting listed apps to run.
</p>
</div>

<?php

// promotional buttons
echo "<center>\n";
echo "<table>\n";
echo "<tr>\n";
echo "<td style='padding:10px;'>\n";
echo '<a href="http://getfirefox.com/"
	title="Get Firefox - Web browsing redefined."><img
	src="http://www.mozilla.org/products/firefox/buttons/getfirefox_large2.png"
	width="178" height="60" border="0" alt="Get Firefox"></a>'."\n";
echo "</td>\n";
echo "<td style='padding:10px;'>\n";
echo '<a href="http://xinha.python-hosting.com/" title="Xinha textarea replacement">
      <img src="images/xinha-red-95.png" width="95" height="100" alt="Xinha"></a>'."\n";
echo "</td>\n";
echo "</tr>\n";
echo "</table>\n";
echo "</center>\n";

apidb_footer();
?>
