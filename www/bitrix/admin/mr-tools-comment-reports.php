<?php

if (is_file($_SERVER['DOCUMENT_ROOT'] . '/local/modules/metaratings.tools/admin/comment-reports.php')) {
    /** @noinspection PhpIncludeInspection */
    require $_SERVER['DOCUMENT_ROOT'] . '/local/modules/metaratings.tools/admin/comment-reports.php';
} else {
    /** @noinspection PhpIncludeInspection */
    require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/metaratings.tools/admin/comment-reports.php';
}