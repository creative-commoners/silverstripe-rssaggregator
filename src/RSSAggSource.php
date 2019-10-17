<?php

namespace Silverstripe\RSSAggregator;

use SilverStripe\ORM\DataObject;
use Silverstripe\RSSAggregator\RSSAggregationPage;

class RSSAggSource extends DataObject {
	private static $has_one = array(
		"Page" => RSSAggregationPage::class,
	);

	private static $db = array(
		"Title" => "Varchar(255)",
		"RSSFeed" => "Varchar(255)",
		"LastChecked" => "DBDatetime",
	);

	private static $singular_name = 'RSS Source';

	private static $plural_name = 'RSS Sources';
}
