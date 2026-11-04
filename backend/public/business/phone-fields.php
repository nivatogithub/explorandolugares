<?php
// Compatibility adapter for the editor and administration; new views receive prepared values.
require_once dirname(__DIR__,2).'/app/BusinessPhonePresentation.php';
BusinessPhonePresentation::render(BusinessPhonePresentation::context($phoneValues??[],$_POST['business_phone']??null));
