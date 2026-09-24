<?php

declare(strict_types=1);

/*
 * This file is part of the UhifadhiLabs Devkit Module.
 *
 * (c) Ezekiel Mjema <https://github.com/eemjema>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Uhifadhi\Devkit\Fleet;

/**
 * THE KINDS OF STEP THE GATE PERFORMS.
 *
 * Most of the install is a command in a directory, which is why {@see Shell}
 * carries the plan. The rest is the half of an install a shell cannot perform:
 * a database made from nothing, a server, and an administrator working the
 * screens in a browser — the difference between "the package is in the lock
 * file" and "somebody can open it".
 */
enum GateStepKind
{
    /** Run an argv, in the project or beside it, and fail on a non-zero exit. */
    case Shell;

    /** Drop and recreate the database the step's subject names. */
    case FreshDatabase;

    /** Write the project's .env.local: the databases the gate made. */
    case WriteEnvironment;

    /** Serve the project, or serve it again after an install changed it. */
    case Serve;

    /** Sign in over HTTP as the administrator the gate created. */
    case SignIn;

    /** Create the area every module step then works inside. */
    case CreateArea;

    /** Switch the module on for the area through the grid's own form, then open its first page. */
    case OpenModule;

    /** The starter's own list of official modules and the configured one are the same list. */
    case ReadmeListsModules;
}
