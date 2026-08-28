<?php
class WorkingMonth {
 public static function resolve($value=null):string {
  $key='working_month_'.TenantGuard::tenantId();
  $value=substr(trim((string)$value),0,7);
  if(preg_match('/^\d{4}-(0[1-9]|1[0-2])$/',$value))$_SESSION[$key]=$value;
  return $_SESSION[$key]??date('Y-m');
 }
}
