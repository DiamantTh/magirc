<?php

namespace MagIRC\Statistics\Base;

use MagIRC\Bootstrap\Application;

abstract class ChannelModel
{
    public $channel;
    public $users;
    public $users_max;
    public $users_max_time;
    public $topic;
    public $topic_html;
    public $topic_author;
    public $topic_time;
    public $kicks;
    public $modes;
    public $modes_data;
    public $DT_RowId;

    public function __construct()
    {
        $this->DT_RowId = $this->channel;
        $this->topic_html = $this->topic ? Application::irc2html($this->topic) : null;
        $this->topic = htmlentities($this->topic, ENT_COMPAT, "UTF-8");
    }
}
