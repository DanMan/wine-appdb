<?php
/*********************/
/* Edit Account Form */
/*********************/
?>

<!-- start of edit account form -->
<tr>
    <td> &nbsp; User Name </td>
    <td> <b> <?php echo $ext_username; ?> </b> </td>
</tr>
<tr>
    <td> &nbsp; Password </td>
    <td> <input type="password" name="ext_password"> </td>
</tr>
<tr>
    <td> &nbsp; Password (again) </td>
    <td> <input type="password" name="ext_password2"> </td>
</tr>
<tr>
    <td> &nbsp; Real Name </td>
    <td> <input type="text" name="ext_realname" value="<?php echo $ext_realname; ?>"> </td>
</tr>
<tr>
    <td> &nbsp; Email Address </td>
    <td> <input type="text" name="ext_email" value="<?php echo $ext_email; ?>"> </td>
</tr>
<tr>
    <td colspan=2>&nbsp;</td>
</tr>

<!-- end of edit account form -->
