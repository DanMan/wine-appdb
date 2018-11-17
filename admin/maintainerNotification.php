<?php

require("path.php");
require(BASE."include/incl.php");


// module used to debug the maintainer notification system
// TODO: integrate this properly in with the objectManager system
//       and with the normal admin menus on the site

apidb_header("Maintiner notification info");

// display all currently notified maintainers
$hResult = maintainer::objectGetEntries(false, false);
echo "Maintainers with a non-zero notification level<br>\n";

$bFoundNonZero = false;
while($oRow = query_fetch_object($hResult))
{
  $oMaintainer = new maintainer(null, $oRow);

  if($oMaintainer->iNotificationLevel != 0)
  {
    $bFoundNonZero = true;
    echo "iMaintainerId: $oMaintainer->iMaintainerId<br>";
    echo "iNotificationLevel: $oMaintainer->iNotificationLevel<br>";
    echo "<br>\n";
  }
}

if(!$bFoundNonZero)
{
  echo "No maintainers have a non-zero notification level<br>\n";
}


echo "<br>\n"; 
echo "<br>\n"; 


// retrieve all of the maintainers
echo "Maintainers with notification iTargetLevel != 0<br>\n";
$hResult = maintainer::objectGetEntries(false, false);
while($oRow = query_fetch_object($hResult))
{
  $oMaintainer = new maintainer(null, $oRow);

  $oNotificationUpdate = $oMaintainer->fetchNotificationUpdate();

  if($oNotificationUpdate->iTargetLevel != 0)
  {
    echo "iMaintainerId: $oMaintainer->iMaintainerId<br>\n";
    echo "iNotificationLevel: $oMaintainer->iNotificationLevel<br>\n";
    echo "iTargetLevel: $oNotificationUpdate->iTargetLevel<br>\n";
    echo "<br>\n";
  }
}

apidb_footer();

