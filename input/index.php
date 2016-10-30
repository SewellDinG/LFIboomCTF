<html>
    <title>asdf</title>
<?php
$flag='php://input';     
extract($_GET);     
if(isset($shiyan)){        
    $content=trim(file_get_contents($flag));
    if($shiyan==$content){ 
       echo'flag{php://input}';     }
     else{       
       echo'Oh.no';}   
     }
?>
</html>
