<?php
/***********************************************************/
/* this class represents an application incl. all versions */
/***********************************************************/

class Application {

    var $data;

    function Application($id)
    {
	$result = query_appdb("SELECT * FROM appFamily WHERE appId = $id");
	if(!$result)
	    return; // Oops
	if(mysql_num_rows($result) != 1)
	    return; // Not found

	$this->data = mysql_fetch_object($result);
    }


    function getAppVersionList()
    {
	$list = array();

	$result = query_appdb("SELECT * FROM appVersion ".
			      "WHERE appId = ". $this->data->appId . " " .
			      "ORDER BY versionName");
	if(!$result)
	    return $list;

	while($row = mysql_fetch_object($result))
	    {
		if($row->versionName == "NONAME")
		    continue;
		$list[] = $row;
	    }

	return $list;
    }

    function getAppVersion($versionId)
    {
	$result = query_appdb("SELECT * FROM appVersion ".
                              "WHERE appId = ". $this->data->appId ." AND ".
			      "versionId = $versionId");
        if(!$result || mysql_num_rows($result) != 1)
            return 0;

        return mysql_fetch_object($result);
    }

    function getVendor()
    {
	$result = query_appdb("SELECT * FROM vendor ".
			      "WHERE vendorId = ". $this->data->vendorId);
	if(!$result || mysql_num_rows($result) != 1)
	    return array("vendorName" => "Unknown");

	$vendor = mysql_fetch_object($result);
	return $vendor;
    }

    function getComments($versionId = 0)
    {
	$list = array();

        $result = query_appdb("SELECT * FROM appComments ".
                              "WHERE appId = ". $this->data->appId . " AND " .
			      "versionId = $versionId " .
                              "ORDER BY time");
        if(!$result)
            return $list;
	
        while($row = mysql_fetch_object($result))
            $list[] = $row;
	
        return $list;
    }
}

function deleteAppFamily($appId)
{
    $r = query_appdb("DELETE FROM appFamily WHERE appId = $appId");
    if($r)
    {
        $r = query_appdb("DELETE FROM appVersion WHERE appId = $appId");
        if($r)
            addmsg("Application and versions deleted", "green");
        else
            addmsg("Failed to delete appVersions: " . mysql_error(), "red");
    }
    else
        addmsg("Failed to delete appFamily $appId: " . mysql_error(), "red");
    
}

function deleteAppVersion($versionId)
{
    $r = query_appdb("DELETE FROM appVersion WHERE versionId = $versionId");
    if($r)
        addmsg("Application Version $versionId deleted", "green");
    else
        addmsg("Failed to delete appVersion $versionId: " . mysql_error(), "red");
}

function lookupVersionName($appId, $versionId)
{
    $result = query_appdb("SELECT versionName FROM appVersion WHERE versionId = $versionId and appId = $appId");
    if(!$result || mysql_num_rows($result) != 1)
        return null;
    $ob = mysql_fetch_object($result);
    return $ob->versionName;
}


function lookupAppName($appId)
{
    $result = query_appdb("SELECT appName FROM appFamily WHERE appId = $appId");
    if(!$result || mysql_num_rows($result) != 1)
        return null;
    $ob = mysql_fetch_object($result);
    return $ob->appName;
}

?>
