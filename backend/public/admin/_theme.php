<?php
// Private administration is a distinct layout; it shares only brand/tokens.
require_once dirname(__DIR__).'/_portal-shell.php';
echo PortalPresentation::assets(false,'admin');
