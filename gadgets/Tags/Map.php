<?php
/**
 * Tags URL maps
 *
 * @category   GadgetMaps
 * @package    Tags
 * @author     Mojtaba Ebrahimi <ebrahimi@zehneziba.ir>
 * @copyright  2013 Jaws Development Group
 * @license    http://www.gnu.org/copyleft/lesser.html
 */
$maps[] = array(
    'ViewTag',
    'tag/{tag}/id/{id}]',
    array(
        'id'    => '[[:digit:]]+',
    )
);
