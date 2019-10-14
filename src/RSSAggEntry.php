<?php

namespace Silverstripe\RSSAggregator;


use EventPage_Image;
use NewsArticle_ArticleImage;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBBoolean;
use SilverStripe\ORM\FieldType\DBDate;
use Silverstripe\RSSAggregator\RSSAggregationPage;
use Silverstripe\RSSAggregator\RSSAggSource;



class RSSAggEntry extends DataObject {
	public static $has_one = array(
		"Page" => RSSAggregationPage::class,
		"Source" => RSSAggSource::class,
	);

	public static $db = array(
		"Displayed" => DBBoolean::class,
		"Date" => "SSDatetime",
		"Title" => "Varchar(255)",
		"Content" => "HTMLText",
		"Permalink" => "Varchar(255)",
		"EnclosureURL" => "Varchar(255)",
	);

	public static $casting = array(
		"PlainContentSummary" => "Text",
	);

	private static $singular_name = 'RSS Aggregate Entry';

	private static $plural_name = 'RSS Aggregate Entry';

	/*
	 * Set new feed item to true if feed moderation is turned off
	 */
	public function populateDefaults() {
		parent::populateDefaults();
		if ( !RSSAggregatingPage::get_moderation_required() ) {
			$this->Displayed = true;
		}
	}


	public function getPlainContentSummary() {
		$content = trim(
			strip_tags(
				ereg_replace("&#[0-9]+;", " ",
					str_replace(array("<p>","<br/>","<br />", "<br>"), array("\n\n","\n","\n","\n"),
						ereg_replace("[\t\r\n ]+", " ", $this->Content)
					)
				)
			)
		);

		$parts = explode("\n\n", $content, 2);
		return $parts[0];
	}

	public function isNews() {
		if($source = DataObject::get_by_id(SiteTree::class, $this->PageID)) {
			if($source->URLSegment == 'aggregated-news') {
				return true;
			}
		}
	}

	public function Image() {
		if($this->isNews()) {
			$img = new NewsArticle_ArticleImage();
		} else {
			$img = new EventPage_Image();
		}
		$img->Filename = $this->EnclosureURL;
		return $img;
	}

	public function getDateNice() {
		return $this->obj(DBDate::class)->Nice();
	}

	public function getSourceNice() {
		$sourceID = $this->SourceID;
		$Source = DataObject::get_by_id(RSSAggSource::class,$sourceID);
		if($Source) return $Source->Title;
		return;
	}

	public function Link() {
		return $this->Permalink;
	}

	public function Permalink() {
		return str_replace('&amp;', '&', $this->Permalink);
	}

	// These functions are included for improved compatability with SiteTree
	public function MenuTitle() {
		return $this->Title;
	}

	public function LinkOrCurrent() {
		return "link";
	}
	public function LinkingMode() {
		return "link";
	}
}
