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

use Udoh\Emsa\Auth\Authenticator;

/**
 * Container for navigation-related page elements
 *
 * @author Josh Ridderhoff <jridderhoff@utah.gov>
 */
class NavigationHeader
{

    /**
     * Generate the HTML5 NAV menu for EMSA
     * 
     * @param NavigationItem[] $navItems
     * @param string           $mainUrl
     * @param int              $selectedPage
     * @param int              $submenu
     * @param int              $cat
     * @param int              $subcat
     * 
     * @return string
     */
    public static function generateNav(array $navItems, ?string $mainUrl = MAIN_URL, ?int $selectedPage = 0, ?int $submenu = 0, ?int $cat = 0, ?int $subcat = 0): string
    {
        $navHtml = "\t\t\t<nav aria-label='EMSA Navigation'>\n";
        $navHtml .= "\t\t\t\t<ul role='menubar' id='emsa-nav-menubar'>";
        $itemCount = 0;

        foreach ($navItems as $navItem) {
            $navHtml .= self::drawHorizontalNavItem($navItem, $mainUrl, 0, $selectedPage, $submenu, $cat, $subcat, $itemCount);
            $itemCount++;
        }
        $navHtml .= "\t\t\t\t</ul>\n";
        $navHtml .= "\t\t\t</nav>\n";

        // menubar keyboard accessibility
        $navHtml .= "\t\t\t<script type=\"text/javascript\">\n";
        $navHtml .= "\t\t\t\tvar menubar = new Menubar(document.getElementById('emsa-nav-menubar'));\n";
        $navHtml .= "\t\t\t\tmenubar.init();\n";
        $navHtml .= "\t\t\t</script>\n";

        return $navHtml;
    }

    /**
     * @param NavigationItem $navItem
     * @param string         $mainUrl
     * @param int            $indent
     * @param int            $selectedPage
     * @param int            $submenu
     * @param int            $cat
     * @param int            $subcat
     *
     * @return string
     */
    private static function buildHorizontalNav(NavigationItem $navItem, ?string $mainUrl = MAIN_URL, ?int $indent = 0, ?int $selectedPage = 0, ?int $submenu = 0, ?int $cat = 0, ?int $subcat = 0): string
    {
        if ($navItem->getRequiredPermissionLevel() > 0) {
            if (!Authenticator::userHasPermission($navItem->getRequiredPermissionLevel())) {
                return "";
            }
        }

        $navStr = "\n";
        $navStr .= self::buildVerticalNav($navItem, $mainUrl, true, $indent, $selectedPage, $submenu, $cat, $subcat);

        foreach ($navItem->getChildren() as $childNavItem) {
            if ($childNavItem->hasChildren() && self::isNodeInBreadcrumb($childNavItem, $selectedPage, $submenu, $cat, $subcat)) {
                $navStr .= "\n";
                $navStr .= self::buildHorizontalNav($childNavItem, $mainUrl, $indent, $selectedPage, $submenu, $cat, $subcat);
            }
        }

        if ($navItem->hasChildren() && self::isDeepestNode($navItem, $selectedPage, $submenu, $cat, $subcat)) {
            foreach ($navItem->getChildren() as $childHorizontalItem) {
                $navStr .= self::drawHorizontalNavItem($childHorizontalItem, $mainUrl, $indent, $selectedPage, $submenu, $cat, $subcat);
            }
        }

        return $navStr;
    }

    private static function drawHorizontalNavItem(NavigationItem $navItem, $mainUrl = MAIN_URL, $indent = 0, $selectedPage = 0, $submenu = 0, $cat = 0, $subcat = 0, $itemCount = 0)
    {
        if ($navItem->getRequiredPermissionLevel() > 0) {
            if (!Authenticator::userHasPermission($navItem->getRequiredPermissionLevel())) {
                return "";
            }
        }

        if (empty($itemCount)) {
            $tabIndex = 0;
        } else {
            $tabIndex = -1;
        }

        $navStr = "\n";
        for ($i = 0; $i <= $indent; $i++) {
            $navStr .= "\t";
        }

        $navStr .= "<li role='none'>";

        if ($navItem->hasChildren()) {
            // folder, don't link
            $navStr .= '<span role="menuitem" aria-haspopup="true" aria-expanded="false" tabindex="' . (int) $tabIndex . '" class="nav-folder ui-corner-all">' . $navItem->getLinkText() . ' &#9660;</span>';
        } else {
            $navStr .= '<a role="menuitem" tabindex="' . (int) $tabIndex . '" href="' . $mainUrl . '/';
            if (intval($navItem->getSelectedPage()) > 0) {
                $navStr .= '?selected_page=' . intval($navItem->getSelectedPage());
            }
            if (intval($navItem->getSubmenu()) > 0) {
                $navStr .= '&submenu=' . intval($navItem->getSubmenu());
            }
            if (intval($navItem->getCat()) > 0) {
                $navStr .= '&cat=' . intval($navItem->getCat());
            }
            if (intval($navItem->getSubcat()) > 0) {
                $navStr .= '&subcat=' . intval($navItem->getSubcat());
            }
            if (intval($navItem->getType()) > 0) {
                $navStr .= '&type=' . intval($navItem->getType());
            }
            $navStr .= '">' . $navItem->getLinkText() . '</a>';
        }

        if ($navItem->hasChildren()) {
            $indent++;
            $navStr .= "\n";
            for ($i = 0; $i <= $indent; $i++) {
                $navStr .= "\t";
            }
            $navStr .= "<ul role='menu'>";
            foreach ($navItem->getChildren() as $childNavItem) {
                $navStr .= self::buildVerticalNav($childNavItem, $mainUrl, false, $indent, $selectedPage, $submenu, $cat, $subcat);
            }
            $navStr .= "\n";
            for ($i = 0; $i <= $indent; $i++) {
                $navStr .= "\t";
            }
            $navStr .= "</ul>\n";
            $indent--;
            for ($i = 0; $i <= $indent; $i++) {
                $navStr .= "\t";
            }
            $indent++;
        }

        $navStr .= "</li>\n";

        return $navStr;
    }

    private static function buildVerticalNav(NavigationItem $navItem, $mainUrl = MAIN_URL, $addDivider = false, $indent = 0, $selectedPage = 0, $submenu = 0, $cat = 0, $subcat = 0)
    {
        if ($navItem->getRequiredPermissionLevel() > 0) {
            if (!Authenticator::userHasPermission($navItem->getRequiredPermissionLevel())) {
                return "";
            }
        }

        $indent++;

        $navStr = "\n";
        for ($i = 0; $i <= $indent; $i++) {
            $navStr .= "\t";
        }

        $navStr .= "<li role='none'>";

        if ($navItem->hasChildren()) {
            // folder, don't link
            $navStr .= '<span role="menuitem" aria-haspopup="true" aria-expanded="false" tabindex="-1" class="nav-folder ui-corner-all">' . $navItem->getLinkText() . ' &#9658;</span>';
        } else {
            $navStr .= '<a role="menuitem" tabindex="-1" href="' . $mainUrl . '/';
            if (intval($navItem->getSelectedPage()) > 0) {
                $navStr .= '?selected_page=' . intval($navItem->getSelectedPage());
            }
            if (intval($navItem->getSubmenu()) > 0) {
                $navStr .= '&submenu=' . intval($navItem->getSubmenu());
            }
            if (intval($navItem->getCat()) > 0) {
                $navStr .= '&cat=' . intval($navItem->getCat());
            }
            if (intval($navItem->getSubcat()) > 0) {
                $navStr .= '&subcat=' . intval($navItem->getSubcat());
            }
            if (intval($navItem->getType()) > 0) {
                $navStr .= '&type=' . intval($navItem->getType());
            }
            $navStr .= '">' . $navItem->getLinkText() . '</a>';
        }

        if ($navItem->hasChildren()) {
            $indent++;
            $navStr .= "\n";
            for ($i = 0; $i <= $indent; $i++) {
                $navStr .= "\t";
            }
            $navStr .= "<ul>";
            foreach ($navItem->getChildren() as $childNavItem) {
                $navStr .= self::buildVerticalNav($childNavItem, $mainUrl, false, $indent, $selectedPage, $submenu, $cat, $subcat);
            }
            $navStr .= "\n";
            for ($i = 0; $i <= $indent; $i++) {
                $navStr .= "\t";
            }
            $navStr .= "</ul>\n";

            if ($addDivider) {
                for ($i = 0; $i <= $indent; $i++) {
                    $navStr .= "\t";
                }
                $navStr .= '<span class="menu_divider">&raquo;</span>' . "\n";
            }
            $indent--;
            for ($i = 0; $i <= $indent; $i++) {
                $navStr .= "\t";
            }
            $indent++;
        }

        $navStr .= '</li>';

        return $navStr;
    }

    private static function isDeepestNode(NavigationItem $currentNavItem, $selectedPage = 0, $submenu = 0, $cat = 0, $subcat = 0)
    {
        if ($currentNavItem->hasChildren()) {
            foreach ($currentNavItem->getChildren() as $currentChild) {
                if (
                        (intval($currentChild->getSelectedPage()) == intval($selectedPage)) && (intval($currentChild->getSubmenu()) == intval($submenu)) && (intval($currentChild->getCat()) == intval($cat)) && (intval($currentChild->getSubcat()) == intval($subcat))
                ) {
                    if (!self::isDeepestNode($currentChild, $selectedPage, $submenu, $cat, $subcat)) {
                        if ($currentChild->hasChildren()) {
                            foreach ($currentChild->getChildren() as $currentGrandchild) {
                                if (self::isDeepestNode($currentGrandchild, $selectedPage, $submenu, $cat, $subcat)) {
                                    return false;
                                }
                            }
                        } else {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

    private static function isNodeInBreadcrumb(NavigationItem $currentNavItem, $selectedPage = 0, $submenu = 0, $cat = 0, $subcat = 0)
    {
        $inBreadcrumb = false;

        if ($currentNavItem->hasChildren()) {
            foreach ($currentNavItem->getChildren() as $childNavItem) {
                if (self::isNodeInBreadcrumb($childNavItem, $selectedPage, $submenu, $cat, $subcat)) {
                    $inBreadcrumb = true;
                    break;
                }
            }
        } else {
            if (
                    (intval($currentNavItem->getSelectedPage()) == intval($selectedPage)) && (intval($currentNavItem->getSubmenu()) == intval($submenu)) && (intval($currentNavItem->getCat()) == intval($cat)) && (intval($currentNavItem->getSubcat()) == intval($subcat))
            ) {
                $inBreadcrumb = true;
            }
        }

        return $inBreadcrumb;
    }

}
