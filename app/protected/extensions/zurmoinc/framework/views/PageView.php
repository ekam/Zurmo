<?php
    /*********************************************************************************
     * Zurmo is a customer relationship management program developed by
     * Zurmo, Inc. Copyright (C) 2012 Zurmo Inc.
     *
     * Zurmo is free software; you can redistribute it and/or modify it under
     * the terms of the GNU General Public License version 3 as published by the
     * Free Software Foundation with the addition of the following permission added
     * to Section 15 as permitted in Section 7(a): FOR ANY PART OF THE COVERED WORK
     * IN WHICH THE COPYRIGHT IS OWNED BY ZURMO, ZURMO DISCLAIMS THE WARRANTY
     * OF NON INFRINGEMENT OF THIRD PARTY RIGHTS.
     *
     * Zurmo is distributed in the hope that it will be useful, but WITHOUT
     * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
     * FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more
     * details.
     *
     * You should have received a copy of the GNU General Public License along with
     * this program; if not, see http://www.gnu.org/licenses or write to the Free
     * Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA
     * 02110-1301 USA.
     *
     * You can contact Zurmo, Inc. with a mailing address at 113 McHenry Road Suite 207,
     * Buffalo Grove, IL 60089, USA. or at email address contact@zurmo.com.
     ********************************************************************************/

    /**
     * The view that forms the basis of every page. It renders
     * the XHtml html, header, body, etc, and renders its contained
     * view within the body. After rending the page and before
     * returning it it validates the XHtml against the XHtml schema
     * and renders directly to the browser any errors it finds
     * before returning the rendered page to the caller.
     */
    class PageView extends View
    {
        /**
         * Flags that the error handler was called.
         */
        public static $foundErrors = false;

        public static $xhtmlValidationErrors = array();

        private $containedView;

        /**
         * Constructs the page view specifying the view that it
         * will contain.
         */
        public function __construct(View $containedView)
        {
            $this->containedView = $containedView;
        }

        public function render()
        {
            if (SHOW_PERFORMANCE)
            {
                $startTime = microtime(true);
            }
            static::registerAllPagesScriptFiles();
            $content = $this->renderXHtmlStart()     .
                       $this->renderXHtmlHead()      .
                       $this->renderXHtmlBodyStart() .
                       parent::render()              .
                       $this->renderXHtmlBodyEnd()   .
                       $this->renderXHtmlEnd();
            Yii::app()->getClientScript()->render($content);
            $performanceMessage = null;
            if (YII_DEBUG && SHOW_PERFORMANCE && Yii::app()->isApplicationInstalled())
            {
                $endTime = microtime(true);
                $performanceMessage .= 'Page render time: ' . number_format(($endTime - $startTime), 3) . ' seconds.<br />';
            }
            if (YII_DEBUG)
            {
                $this->validate($content);
                if (!empty(self::$xhtmlValidationErrors))
                {
                    foreach (self::$xhtmlValidationErrors as $error)
                    {
                        $content = $this->appendContentBeforeXHtmlBodyEndAndXHtmlEnd($content, $error);
                    }
                }
                if (SHOW_PERFORMANCE && Yii::app()->isApplicationInstalled())
                {
                    $endTime      = microtime(true);
                    $endTotalTime = Yii::app()->performance->endClockAndGet();
                    $performanceMessage .= '<span>Total page view time including validation: ' . number_format(($endTime - $startTime), 3) . ' seconds.</span><br />';
                    $performanceMessage .= '<span>Total page time: ' . number_format(($endTotalTime), 3) . ' seconds.</span><br />';
                }
            }
            else
            {
                if (SHOW_PERFORMANCE && Yii::app()->isApplicationInstalled())
                {
                    $endTime      = microtime(true);
                    $endTotalTime = Yii::app()->performance->endClockAndGet();
                    $performanceMessage .= 'Load time: ' . number_format(($endTotalTime), 3) . ' seconds.<br />';
                }
            }
            if (SHOW_PERFORMANCE && Yii::app()->isApplicationInstalled())
            {
                if (SHOW_QUERY_DATA)
                {
                    $performanceMessage .= self::makeShowQueryDataContent();
                }
                foreach (Yii::app()->performance->getTimings() as $id => $time)
                {
                    $performanceMessage .= 'Timing: ' . $id . ' total time: ' . number_format(($time), 3) . "</br>";
                }
                $performanceMessageHtml = '<div class="performance-info">' . $performanceMessage . '</div>';
                $content = $this->appendContentBeforeXHtmlBodyEndAndXHtmlEnd($content, $performanceMessageHtml);
            }
            if (YII_DEBUG && Yii::app()->isApplicationInstalled())
            {
                $dbInfoHtml = '<span style="background-color: lightgreen; color: green">Database: \'' . Yii::app()->db->connectionString . '\', username: \'' . Yii::app()->db->username . '\'.</span><br />';
                $content = $this->appendContentBeforeXHtmlBodyEndAndXHtmlEnd($content, $dbInfoHtml);
            }
            return $content;
        }

        /**
         * Validates the page content against the XHTML schema
         * and writes the problems directly to output in bright
         * red on yellow. Is public for access by unit tests.
         */
        public static function validate($content)
        {
            $valid = false;

            set_error_handler(array('PageView', 'schemeValidationErrorHandler'));
            $domDocument = new DomDocument();
            $xHtmlDtd = str_replace('\\', '/', dirname(__FILE__)) . '/../resources/xhtml1-transitional.dtd';

            $document = new DOMDocument();
            $document->loadXML($content);
            $rootNode = $document->getElementsByTagName('html')->item(0);

            if ($rootNode !== null && !self::$foundErrors)
            {
                $implementation = new DOMImplementation();
                $documentType         = $implementation->createDocumentType('html', null, $xHtmlDtd);
                $documentWithLocalDtd = $implementation->createDocument(null, null, $documentType);
                $documentWithLocalDtd->encoding = "utf-8";
                $rootNodeWithLocalDtd = $documentWithLocalDtd->importNode($rootNode, true);
                $documentWithLocalDtd->appendChild($rootNodeWithLocalDtd);
                $valid = $documentWithLocalDtd->validate() && !self::$foundErrors;
            }
            else
            {
                self::$xhtmlValidationErrors[] = '<span style="background-color: yellow; color: #c00000">Loading found errors, skipping validation.</span><br />';
            }

            restore_error_handler();

            return $valid;
        }

        /**
         * Error handler that writes the errors directly to
         * output in bright red on yellow.
         */
        public static function schemeValidationErrorHandler($errno, $errstr, $errfile, $errline)
        {
            static $first = true;

            if ($first)
            {
                self::$xhtmlValidationErrors[] = '<span style="background-color: yellow; color: #c00000;"><b>THIS IS NOT A VALID XHTML FILE</b></span><br />';
                $first = false;
            }
            self::$xhtmlValidationErrors[] = "<span style=\"background-color: yellow; color: #c00000;\">$errstr</span><br />";

            self::$foundErrors = true;
        }

        protected function renderContent()
        {
            return $this->containedView->render();
        }

        /**
         * Renders the xml declaration, doctype, and the html start tag.
         */
        protected function renderXHtmlStart()
        {
            $themeUrl = Yii::app()->baseUrl . '/themes';
            $theme    = Yii::app()->theme->name;
            if (!MINIFY_SCRIPTS && Yii::app()->isApplicationInstalled())
            {
                Yii::app()->clientScript->registerScriptFile(
                    Yii::app()->getAssetManager()->publish(
                        Yii::getPathOfAlias('ext.zurmoinc.framework.views.assets')) . '/less-1.2.0.min.js');
            }
            Yii::app()->clientScript->registerScriptFile(
                Yii::app()->getAssetManager()->publish(
                    Yii::getPathOfAlias('ext.zurmoinc.framework.views.assets')) . '/interactions.js');
            return '<?xml version="1.0" encoding="utf-8"?>'.
                   '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">' .
                   '<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">';
        }

        /**
         * Renders the XHtml header element containing the title
         * and the default stylesheets screen, print, and ie. Additional
         * stylesheets can be specified by overriding getStyles() in
         * the extending class.
         */
        protected function renderXHtmlHead()
        {
            $title    = Yii::app()->format->text(trim($this->getTitle()));
            $subtitle = Yii::app()->format->text(trim($this->getSubtitle()));
            if ($subtitle != '')
            {
                $title = "$title - $subtitle";
            }
            $defaultTheme = 'themes/default';
            $theme        = 'themes/' . Yii::app()->theme->name;
            $cs = Yii::app()->getClientScript();
            $cs->registerMetaTag('text/html; charset=UTF-8', null, 'Content-Type'); // Not Coding Standard

            $specialCssContent = null;
            if (!MINIFY_SCRIPTS && Yii::app()->isApplicationInstalled())
            {
                $specialCssContent .= '<link rel="stylesheet/less" type="text/css" href="' .
                                      Yii::app()->baseUrl . '/' . $theme . '/less/newui.less"/>';
                $specialCssContent .= '<!--[if lt IE 10]><link rel="stylesheet/less" type="text/css" href="' .
                                      Yii::app()->baseUrl . '/' . $theme . '/less/ie.less"/><![endif]-->';
            }
            else
            {
                $cs->registerCssFile(Yii::app()->baseUrl . '/' . $theme . '/css/newui.css');
            }
            if (MINIFY_SCRIPTS)
            {
                Yii::app()->minScript->generateScriptMap('css');
                if (!YII_DEBUG && !defined('IS_TEST'))
                {
                    Yii::app()->minScript->generateScriptMap('js');
                }
            }
            if (Yii::app()->browser->getName() == 'msie' && Yii::app()->browser->getVersion() < 8)
            {
                $cs->registerCssFile(Yii::app()->baseUrl . '/' . $theme . '/css' . '/ie.css', 'screen, projection');
            }

            foreach ($this->getStyles() as $style)
            {
                if ($style != 'ie')
                {
                    if (file_exists("$theme/css/$style.css"))
                    {
                        $cs->registerCssFile(Yii::app()->baseUrl . '/' . $theme . '/css/' . $style. '.css'); // Not Coding Standard
                    }
                }
            }

            if (file_exists("$theme/ico/favicon.ico"))
            {
                $cs->registerLinkTag('shortcut icon', null, Yii::app()->baseUrl . '/' . $theme . '/ico/favicon.ico');
            }
            else
            {
                $cs->registerLinkTag('shortcut icon', null, Yii::app()->baseUrl . '/' . $defaultTheme . '/ico/favicon.ico');
            }
            return '<head>' .
                 '<meta http-equiv="X-UA-Compatible" content="IE=edge" />' . // Not Coding Standard
                  $specialCssContent .
                  "<title>$title</title>"  .
                  '</head>';
        }

        /**
         * Returns the application title. Can be overridden in the extending class.
         */
        protected function getTitle()
        {
            return '';
        }

        /**
         * Returns the application subtitle. Can be overridden in the extending class.
         */
        protected function getSubtitle()
        {
            return '';
        }

        /**
         * Returns an empty array of styles, being the names of stylesheets
         * without a css extention. Can be overridden in the extending class
         * to specify stylesheets additional to those rendered by default.
         * @see renderXHtmlHead()
         */
        protected function getStyles()
        {
            return array();
        }

        /**
         * Renders the body start tag.
         */
        protected function renderXHtmlBodyStart()
        {
            return '<body>';
        }

        /**
         * Renders the body end tag.
         */
        protected function renderXHtmlBodyEnd()
        {
            return '</body>';
        }

        /**
         * Renders the html end tag.
         */
        protected function renderXHtmlEnd()
        {
            return '</html>';
        }

        public static function makeShowQueryDataContent()
        {
            $performanceMessage  = 'Total/Duplicate Queries: ' . Yii::app()->performance->getRedBeanQueryLogger()->getQueriesCount();
            $performanceMessage .= '/'   . Yii::app()->performance->getRedBeanQueryLogger()->getDuplicateQueriesCount();
            $duplicateData = Yii::app()->performance->getRedBeanQueryLogger()->getDuplicateQueriesData();
            if (count($duplicateData) > 0)
            {
                $performanceMessage .= '</br></br>Duplicate Queries:</br>';
            }
            foreach ($duplicateData as $query => $count)
            {
                $performanceMessage .= 'Count: ' . $count . '&#160;&#160;&#160;Query: ' . $query . "</br>";
            }
            return $performanceMessage;
        }

        /**
         * Register into clientScript->scriptFiles any scripts that should load on all pages
         * @see getScriptFilesThatLoadOnAllPages
         */
        public static function registerAllPagesScriptFiles()
        {
            Yii::app()->clientScript->registerCoreScript('jquery');
            Yii::app()->clientScript->registerCoreScript('jquery.ui');
        }

        /**
         * @return array of script files that are loaded on all pages @see registerAllPagesScriptFiles
         */
        public static function getScriptFilesThatLoadOnAllPages()
        {
            $scriptData = array();
            if (MINIFY_SCRIPTS)
            {
                foreach (Yii::app()->minScript->usingAjaxShouldNotIncludeJsPathAliasesAndFileNames as $data)
                {
                   $scriptData[] = Yii::app()->getAssetManager()->getPublishedUrl(Yii::getPathOfAlias($data[0])) . $data[1];
                }
            }
            return $scriptData;
        }

        /**
         * Add additional html conent before html body end("</body>") tag and html end tag ("</html>")
         * @param string $content
         * @param string $additionalContent
         * @return string
         */
        public function appendContentBeforeXHtmlBodyEndAndXHtmlEnd($content, $additionalContent)
        {
            $content = str_replace($this->renderXHtmlBodyEnd() . $this->renderXHtmlEnd() ,
                                   $additionalContent . $this->renderXHtmlBodyEnd() . $this->renderXHtmlEnd(),
                                   $content );
            return $content;
        }
    }
?>
