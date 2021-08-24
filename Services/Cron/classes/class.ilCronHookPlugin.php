<?php

/* Copyright (c) 1998-2010 ILIAS open source, Extended GPL, see docs/LICENSE */

include_once("./Services/Component/classes/class.ilPlugin.php");
 
/**
 * Abstract parent class for all cron hook plugin classes.
 *
 * @author Jörg Lützenkirchen <luetzenkirchen@leifos.com>
 * @version $Id$
 *
 * @ingroup ServicesCron
 */
abstract class ilCronHookPlugin extends ilPlugin implements ilCronJobProvider
{
    final public function getComponentType()
    {
        return IL_COMP_SERVICE;
    }

    final public function getComponentName()
    {
        return "Cron";
    }

    final public function getSlot()
    {
        return "CronHook";
    }

    final public function getSlotId()
    {
        return "crnhk";
    }
}
