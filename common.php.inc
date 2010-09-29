<?php

function jaEscape($s)
{
  return mysql_real_escape_string(trim($s));
}

function dbError($query, $error)
{
  if(php_sapi_name() == "cli")
    {
      echo "Error in query: $query\nError:$error\n";
    }
  else
    {
      echo "<div class=\"errorDiv\"><p><strong>Error in query:</strong>$query</p><p><strong>Error:</strong> $error</p></div>\n";
    }
  $query = "ROLLBACK;";
  $result = mysql_query($query) or dberror($query, mysql_error());
  die();
}

?>