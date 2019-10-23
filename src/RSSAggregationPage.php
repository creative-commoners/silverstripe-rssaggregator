<?php

namespace Silverstripe\RSSAggregator;

use Page;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Convert;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RelationEditor;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use Silverstripe\RSSAggregator\RSSAggEntry;
use Silverstripe\RSSAggregator\RSSAggSource;
use SimplePie;

/**
 * RSSAggregationPage lets a CMS Authors aggregate and filter a number of RSS feeds.
 */
class RSSAggregationPage extends Page
{

    private static $table_name = 'RSSAggregationPage';

    private static $db = array (
        "NumberOfItems" => "Int"
    );

    private static $has_many = array(
        "SourceFeeds" => RSSAggSource::class,
        "Entries" => RSSAggEntry::class
    );

    private static $moderation_required = false;

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $config = GridFieldConfig_RelationEditor::create();

        $config->getComponentByType(GridFieldDataColumns::class)->setDisplayFields(array(
            "Title" => "Title",
            "RSSFeed" => "RSSFeedSource"
        ));

        $gridField = new GridField(
            RSSAggSource::class,
            'SourceFeeds',
            $this->SourceFeeds(),
            $config
        );

        $fields->addFieldToTab("Root.Sources", $gridField);

        return $fields;
    }

    /**
     * Use SimplePie to get all the RSS feeds and aggregate them into Entries
     */
    public function updateRSS()
    {

        require_once(Director::baseFolder() . '/rssaggregator/thirdparty/simplepie/autoloader.php');

        $cache = Director::baseFolder() . '/assets/.cache';

        if (!file_exists($cache)) {
            mkdir($cache);
            chmod($cache, 0775);
        }

        if (!is_numeric($this->ID)) {
            return;
        }

        $goodSourceIDs = array();
        foreach ($this->SourceFeeds() as $sourceFeed) {
            $goodSourceIDs[] = $sourceFeed->ID;

            if (isset($_REQUEST['flush']) || strtotime($sourceFeed->LastChecked) < time() - 3600) {
                $simplePie = new SimplePie();
                $simplePie->set_feed_url($sourceFeed->RSSFeed);
                $simplePie->set_cache_location($cache);
                $simplePie->init();
                $sourceFeed->Title = $simplePie->get_title();
                $sourceFeed->LastChecked = date('Y-m-d H:i:s');
                $sourceFeed->write();

                $idClause = '';
                $goodIDs = array();

                $items = $simplePie->get_items();
                if ($items) {
                    foreach ($items as $item) {
                        $entry = new RSSAggEntry();
                        $entry->Permalink = $item->get_permalink();
                        $entry->Date = $item->get_date('Y-m-d H:i:s');
                        $entry->Title = Convert::xml2raw($item->get_title());
                        $entry->Title = str_replace(array(
                            '&nbsp;',
                            '&lsquo;',
                            '&rsquo;',
                            '&ldquo;',
                            '&rdquo;',
                            '&amp;',
                            '&apos;'
                        ), array(
                            '&#160;',
                            "'",
                            "'",
                            '"',
                            '"',
                            '&',
                            '`'
                        ), $entry->Title);

                        $entry->Content = str_replace(array(
                            '&nbsp;',
                            '&lsquo;',
                            '&rsquo;',
                            '&ldquo;',
                            '&rdquo;',
                            '&amp;',
                            '&apos;'
                        ), array(
                            '&#160;',
                            "'",
                            "'",
                            '"',
                            '"',
                            '&',
                            '`'
                        ), $item->get_description());
                        $entry->PageID = $this->ID;
                        $entry->SourceID = $sourceFeed->ID;

                        if ($enclosure = $item->get_enclosure()) {
                            $entry->EnclosureURL = $enclosure->get_link();
                        }

                        $SQL_permalink = Convert::raw2sql($entry->Permalink);
                        $existingID = DB::query(
                            "SELECT \"ID\" FROM \"RSSAggEntry\" WHERE \"Permalink\" = '$SQL_permalink'"
                            . " AND \"SourceID\" = $entry->SourceID AND \"PageID\" = $entry->PageID"
                        )
                            ->value();

                        if ($existingID) {
                            $entry->ID = $existingID;
                        }
                        $entry->write();

                        $goodIDs[] = $entry->ID;
                    }
                }
                if ($goodIDs) {
                    $list_goodIDs = implode(', ', $goodIDs);
                    $idClause = "AND \"ID\" NOT IN ($list_goodIDs)";
                }
                DB::query(
                    "DELETE FROM \"RSSAggEntry\" WHERE \"SourceID\" = $sourceFeed->ID"
                    . " AND \"PageID\" = $this->ID $idClause"
                );
            }
        }
        if ($goodSourceIDs) {
            $list_goodSourceIDs = implode(', ', $goodSourceIDs);
            $sourceIDClause = " AND \"SourceID\" NOT IN ($list_goodSourceIDs)";
        } else {
            $sourceIDClause = '';
        }
        DB::query("DELETE FROM \"RSSAggEntry\" WHERE \"PageID\" = $this->ID $sourceIDClause");
        return;
    }

    public function RSSChildren()
    {
        $this->updateRSS();

        // Tack the RSS feed children to the end of the children provided by the RSS feed
        $c1 = DataObject::get(SiteTree::class, "\"ParentID\" = '$this->ID'");
        $c2 = DataObject::get(RSSAggEntry::class, "\"PageID\" = $this->ID AND \"Displayed\" = 1", "\"Date\" ASC");

        $set = new ArrayList();

        if ($c1) {
            if ($c2) {
                $set->push($c2);
            }
            return $set->push($c1);
        } else {
            return $c2;
        }
    }

    /*
     * Get feed moderation mode
     * @return  boolean
     */
    public function getModerationRequired()
    {
        return Config::inst()->get(static::class, 'moderation_required');
    }
}
