
<!-- End Content -->
</div>
</div>

<div id="footer">
    <a href="https://wiki.winehq.org/WineHQ_Wiki:Privacy_policy">Privacy Policy</a><br><br>
    Hosted By
    <a href="http://www.codeweavers.com/"><img src="https://dl.winehq.org/share/images/cw_logo_sm.png" alt="CodeWeavers"
    title="CodeWeavers - Run Windows applications and games on Mac and Linux"></a>
</div>

<?php
if($GLOBALS['_APPDB_debug'] and !empty($GLOBALS['_APPDB_debugLog'])) {
?>
<div id="dlog">
    <div id="dlogp"><pre><?php echo htmlspecialchars($GLOBALS['_APPDB_debugLog']); ?></pre></div>
    <div id="dlogt"><i class="fa fa-bug"></i> Toggle Debug Console</div>
</div>
<?php
}
?>

</body>
</html>
