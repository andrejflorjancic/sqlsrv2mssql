<?php
error_reporting(-1);
ini_set('display_errors', 'On');
ini_set('mssql.charset', 'cp1250');

DEFINE("SQLSRV_FETCH_ASSOC", MSSQL_ASSOC); 
DEFINE("SQLSRV_FETCH_NUMERIC", MSSQL_NUM);
DEFINE("SQLSRV_FETCH_BOTH",  MSSQL_BOTH);

function mssql_get_proc_params( $function, $conn ) {
    $aName = explode('.', $function);
    $specific_name = array_pop($aName);
    $specific_schema = array_pop($aName);
    $specific_catalog = array_pop($aName);

    $sql = 'select * from information_schema.parameters where ';
    $filter = '';
    if ($specific_catalog) $filter .= " and specific_catalog = '".$specific_catalog."'";
    if ($specific_schema) $filter .= " and specific_schema = '".$specific_schema."'";
    $filter .= " and specific_name = '".$specific_name."' order by ORDINAL_POSITION";
    $sql .= substr($filter, 5);

    $result = mssql_query($sql, $conn);
    return mssql_fetch_array($result);
}

function sqlsrv_connect ( $serverName, $connectionInfo ) {
    $conn = mssql_connect($serverName, $connectionInfo['UID'], $connectionInfo['PWD']);
    mssql_select_db($connectionInfo['Database'], $conn);

    return $conn;
}

function sqlsrv_close ( $conn ) {
    return true;
}

function sqlsrv_errors ( $errorsOrWarnings ) {
    return mssql_get_last_message();
}

function sqlsrv_prepare ( $conn, $sql, $params, $options ) {
    $fName = explode('call ', $sql, 2);
    $fName = explode('(', $fName[1], 1);

    $stmt = mssql_init($stmt);

    $paramsDef = mssql_get_proc_params( $fName, $conn );

    foreach ($params as $key => $param) {
        switch ($paramsDef[$key]['DATA_TYPE']) {
            case "varchar":
                mssql_bind($stmt, $paramsDef[$key]['PARAMETER_NAME'], $param, SQLVARCHAR, ($paramsDef[$key]['PARAMETER_MODE'] == 'IN'?false:true), false, $paramsDef[$key]['CHARACTER_MAXIMUM_LENGTH']);
                break;
            case "datetime":
                mssql_bind($stmt, $paramsDef[$key]['PARAMETER_NAME'], $param, SQLVARCHAR, ($paramsDef[$key]['PARAMETER_MODE'] == 'IN'?false:true), false, 10);
                break;
            case "int":
                mssql_bind($stmt, $paramsDef[$key]['PARAMETER_NAME'], $param, SQLINT1, ($paramsDef[$key]['PARAMETER_MODE'] == 'IN'?false:true), false, $paramsDef[$key]['NUMERIC_PRECISION']);
                break;
        }
    }

    return $stmt;
}

function sqlsrv_execute ( $stmt ) {
    return mssql_execute($stmt);
}

function sqlsrv_query ( $conn, $sql, $params = array(), $options = array() ) {
    foreach($params as $param) {
        if ($param == NULL) {
            $sql = preg_replace('/\?/', 'NULL', $sql, 1);
        }
        else {
            switch (gettype($param)) {
                case "string":
                    $sql = preg_replace('/\?/', "'".str_replace("'", "''", $param)."'", $sql, 1);
                    break;
                default:
                    $sql = preg_replace('/\?/', $param, $sql, 1);
            }
        }
    }

    if (stripos($sql, 'call ') > -1) {
        $sql = str_replace('{','',str_replace('}','',$sql));
        $sql = str_replace('(','',str_replace(')','',$sql));
        $sql = str_ireplace('call','EXEC',$sql);
    }
    try
    {
        $result = mssql_query($sql, $conn);
    }
    catch (Exception $e) {
        print_r($sql."\n");
        die($e);
    }
    if ($result) 
        return $result;
    else {
        print_r($sql."\n");
        die(mssql_get_last_message());
    }
}

function sqlsrv_free_stmt( $stmt ) {
    if (gettype($stmt) == 'resource' )
    try {
        return mssql_free_result($stmt);
    }
    catch (Exception $e) {
        try {
            return mssql_free_statement($stmt);
        }
        catch (Exception $e) {
        }
    }
    else return false;
}

function sqlsrv_fetch_array( $stmt, $fetchType, $row = NULL, $offset = NULL ) {
    if (gettype($stmt) == 'resource' )
        return mssql_fetch_array($stmt, $fetchType);
    else return false;
}
