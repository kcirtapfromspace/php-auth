<?php
/**
 * Copyright (c) 2016 Utah Department of Technology Services and Utah Department of Health
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 * 
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 * 
 * In addition, this program is also subject to certain additional terms. You should
 * have received a copy of these additional terms immediately following the terms and
 * conditions of the GNU Affero General Public License which accompanied the program.
 * If not, please request a copy in writing from the Utah Department of Health at
 * the address below.
 * 
 * If you have questions concerning this license or the applicable additional terms,
 * you may contact us in writing at:
 * Utah Department of Health, P.O. Box 141010, Salt Lake City, UT 84114-1010 USA.
 * 
 * @copyright Copyright (c) 2016 Utah Department of Technology Services and Utah Department of Health
 */

/**
 * Container for elements in the Navigation Header menu.
 *
 * @author Josh Ridderhoff <jridderhoff@utah.gov>
 */
class NavigationItem
{

    /** @var string */
    protected $linkText;

    /** @var int */
    protected $requiredPermissionLevel;

    /** @var int */
    protected $selectedPage;

    /** @var int */
    protected $submenu;

    /** @var int */
    protected $cat;

    /** @var int */
    protected $subcat;

    /** @var int */
    protected $type;

    /** @var NavigationItem[] */
    protected $childItems = array();

    /**
     * Create a new Navigation menu item
     * 
     * @param string $linkText Text to use for the link
     * @param int $requiredPermissionLevel [Optional]<br>Integer value to check permission level against.  If <b>NULL</b>, displays for all users.
     * @param int $selectedPage Identifies the selected page
     * @param int $submenu [Optional]If specified, identifies the selected submenu
     * @param int $cat [Optional]If specified, identifies the selected category
     * @param int $subcat [Optional]If specified, identifies the selected subcategory
     * @param int $type [Optional]If specified, identifies the selected EMSA queue type
     */
    public function __construct($linkText = '[No Text]', $requiredPermissionLevel = null, $selectedPage = null, $submenu = null, $cat = null, $subcat = null, $type = null)
    {
        $this->setLinkText($linkText);
        $this->setRequiredPermissionLevel($requiredPermissionLevel);
        $this->setSelectedPage($selectedPage);
        $this->setSubmenu($submenu);
        $this->setCat($cat);
        $this->setSubcat($subcat);
        $this->setType($type);
    }

    public function getLinkText()
    {
        return $this->linkText;
    }

    public function getSelectedPage()
    {
        return $this->selectedPage;
    }

    public function getSubmenu()
    {
        return $this->submenu;
    }

    public function getCat()
    {
        return $this->cat;
    }

    public function getSubcat()
    {
        return $this->subcat;
    }

    public function getType()
    {
        return $this->type;
    }

    public function getRequiredPermissionLevel()
    {
        return $this->requiredPermissionLevel;
    }

    public function setLinkText($linkText = null)
    {
        if (!EmsaUtils::emptyTrim($linkText)) {
            $this->linkText = filter_var($linkText, FILTER_SANITIZE_STRING);
        }
    }

    public function setSelectedPage($selectedPage = null)
    {
        if (intval($selectedPage) > 0) {
            $this->selectedPage = intval($selectedPage);
        }
    }

    public function setSubmenu($submenu = null)
    {
        if (intval($submenu) > 0) {
            $this->submenu = intval($submenu);
        }
    }

    public function setCat($cat = null)
    {
        if (intval($cat) > 0) {
            $this->cat = intval($cat);
        }
    }

    public function setSubcat($subcat = null)
    {
        if (intval($subcat) > 0) {
            $this->subcat = intval($subcat);
        }
    }

    public function setType($type = null)
    {
        if (intval($type) > 0) {
            $this->type = intval($type);
        }
    }

    public function setRequiredPermissionLevel($requiredPermissionLevel = null)
    {
        if (intval($requiredPermissionLevel) > 0) {
            $this->requiredPermissionLevel = intval($requiredPermissionLevel);
        }
    }

    /**
     * Indicates if this Navigation Item has any child elements.
     * 
     * @return bool
     */
    public function hasChildren()
    {
        if (count($this->childItems) > 0) {
            return true;
        } else {
            return false;
        }
    }

    public function addChild(NavigationItem $navItem)
    {
        $this->childItems[] = $navItem;
        return $this;  // method chaining FTW
    }

    /**
     * Get all child Navigation Items
     * 
     * @return NavigationItem[]
     */
    public function getChildren()
    {
        return $this->childItems;
    }

}
