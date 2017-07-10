<?php
/****************************************************************/
/* Code to view all kinds of interesting statistics about appdb */
/****************************************************************/

require("path.php");
require(BASE."include/incl.php");
require_once(BASE."include/user.php");

apidb_header("AppDB Statistics");

echo "<h1 class=\"whq-app-title\">AppDB Statistics</h1>\n";

echo "<table class=\"whq-table\">\n";
echo "<thead>\n";
echo "<tr>\n";
echo "    <td>Item:</td>\n";
echo "    <td>Stat:</td>\n";
echo "</tr>\n\n";
echo "</thead>\n";
echo "<tbody>\n";

/* Display the number of users */
echo "<tr>\n";
echo "    <td>Users:</td>\n";
echo "    <td>".User::objectGetEntriesCount()."</td>\n";
echo "</tr>\n\n";

/* Display the active users in the last 30 days */
echo "<tr>\n";
echo "    <td>Users active within the last 30 days:</td>\n";
echo "    <td>".User::active_users_within_days(30)."</td>\n";
echo "</tr>\n\n";

/* Display the active users in the last 60 days */
echo "<tr>\n";
echo "    <td>Users active within the last 60 days:</td>\n";
echo "    <td>".User::active_users_within_days(60)."</td>\n";
echo "</tr>\n\n";

/* Display the active users in the last 90 days */
echo "<tr>\n";
echo "    <td>Users active within the last 90 days:</td>\n";
echo "    <td>".User::active_users_within_days(90)."</td>\n";
echo "</tr>\n\n";

/* Display the inactive users */
echo "<tr>\n";
echo "    <td>Inactive users (not logged in since six months):</td>\n";
echo "    <td>".(User::objectGetEntriesCount()-
        User::active_users_within_days(183))."</td>\n";
echo "</tr>\n\n";

/* Display the number of comments */
echo "<tr>\n";
echo "    <td>Comments:</td>\n";
echo "    <td>".getNumberOfComments()."</td>\n";
echo "</tr>\n\n";

/* Display the number of application familes */
echo "<tr>\n";
echo "    <td>Application families:</td>\n";
echo "    <td>".application::objectGetEntriesCount('accepted')."</td>\n";
echo "</tr>\n\n";

/* Display the number of versions */
echo "<tr>\n";
echo "    <td>Versions:</td>\n";
echo "    <td>".version::objectGetEntriesCount('accepted')."</td>\n";
echo "</tr>\n\n";

/* Display the number of application maintainers */
echo "<tr>\n";
echo "    <td>Application maintainers:</td>\n";
echo "    <td>".Maintainer::getNumberOfMaintainers()."</td>\n";
echo "</tr>\n\n";

/* Display the number of test reports */
echo "<tr>\n";
echo "    <td>Test reports:</td>\n";
echo "    <td>".testData::objectGetEntriescount('accepted')."</td>\n";
echo "</tr>\n\n";

/* Display the number of images */
echo "<tr>\n";
echo "    <td>Screenshots:</td>\n";
echo "    <td>".screenshot::objectGetEntriesCount('accepted')."</td>\n";
echo "</tr>\n\n";

echo "</tbody>\n";
echo "</table>\n\n";

apidb_footer();
?>
