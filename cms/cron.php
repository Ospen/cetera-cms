<?php
/**
 * 
 *
 * @version $Id$
 * @copyright 2007 
 **/

set_time_limit(99999);

require('include/common.php');
$application->connectDb();
$application->initPlugins();
$application->cronJob(DOCROOT.'../logs/cron.log');

foreach ($application->getCronJobs() as $file) {

    if (file_exists($file)) 
        include_once($file);

}