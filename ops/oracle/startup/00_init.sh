#!/bin/bash

set -euo pipefail

password=$(cat /run/secrets/oracle_pwd)

sqlplus -s / as sysdba &>/dev/null <<SQL || true
whenever sqlerror exit failure
alter session set container = FREEPDB1;
create user APP identified by "${password}";
exit
SQL

sqlplus -s / as sysdba &>/dev/null <<SQL || true
whenever sqlerror exit failure
alter session set container = FREEPDB1;
alter user APP identified by "${password}";
grant create session, create table, create sequence, create view, create procedure, create trigger to APP;
grant execute on DBMS_LOCK to APP;
alter user APP quota unlimited on users;
exit
SQL

sqlplus -s / as sysdba &>/dev/null <<SQL || true
whenever sqlerror exit failure
alter session set container = FREEPDB1;
create user APP_UNIT identified by "${password}";
exit
SQL

sqlplus -s / as sysdba &>/dev/null <<SQL || true
whenever sqlerror exit failure
alter session set container = FREEPDB1;
alter user APP_UNIT identified by "${password}";
grant create session, create table, create sequence, create view, create procedure, create trigger to APP_UNIT;
grant execute on DBMS_LOCK to APP_UNIT;
alter user APP_UNIT quota unlimited on users;
exit
SQL
