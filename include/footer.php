    <!-- End Content -->
    </div>
  <b class="rbottom"><b class="r4"></b><b class="r3"></b><b class="r2"></b><b class="r1"></b></b>
  </div>

</div>

<div id="footer">
    Hosted By
    <a href="http://www.codeweavers.com/"><img src="<?php echo BASE; ?>images/cw_logo_sm.png" alt="CodeWeavers"
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
