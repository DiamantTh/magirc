<?php

declare(strict_types=1);

namespace MagIRC\Services\Ircd\nefarious;

// Nefarious 13 protocol file for Magirc
class Protocol
{
    public const IRCD = 'nefarious';

    public const CHAN_MODES = 'aciklmnprstzCLMNOQSTZ';
    public const CHAN_MODES_DATA = 'klL';
    public const USER_MODES = 'acdfghiknoqrsxwzBCDHILORWX';

    public const OPER_HIDDEN_MODE = 'H';
    public static $oper_levels = [];
    public const HELPER_MODE = '';
    public const BOT_MODE = 'B';
    public const SERVICES_PROTECTION_MODE = 'k';
    public const CHAN_HIDE_MODE = 'n';
    public const CHAN_SECRET_MODE = 's';
    public const CHAN_PRIVATE_MODE = 'p';

    public const CHAN_EXCEPTION = true;
    public const CHAN_INVITES = false;
    public const LINE_SQ = false;
    public const LINE_G = false;
    public const HOST_CLOAKING = true;
}
