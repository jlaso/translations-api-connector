<?php

namespace JLaso\TranslationsApiConnector\Command;

use Router\SlimExt;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;


/**
 * Sync translations files - translations server.
 *
 * @author Joseluis Laso <jlaso@joseluislaso.es>
 */
class TranslationsExtractCommand extends Command
{
    /** @var InputInterface */
    private $input;
    /** @var OutputInterface */
    private $output;
    private $srcDir;

    const THROWS_EXCEPTION = true;


    const ESCAPE_CHARS = '"';
    /** @var array */
    protected $inputFiles = array();
    /** @var array */
    protected $filters = array(
        'php' => array('PHP'),
        'phtml'	=> array('PHP', 'NetteLatte')
    );
    /** @var array */
    protected $comments = array(
        'Gettext keys exported by JLaso translations api connector  https://github.com/jlaso/translations-api-connector'
    );
    /** @var array */
    protected $meta = array(
        'Content-Type' => 'text/plain; charset=UTF-8',
        'Plural-Forms' => 'nplurals=2; plural=(n != 1);'
    );
    /** @var array */
    protected $data = array();
    /** @var array */
    protected $filterStore = array();



    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('jlaso:translations:extract');
        $this->setDescription('Extract translations keys from php and template files.');

        //$this->addOption('cache-clear', 'c', InputOption::VALUE_NONE, 'Remove translations cache files for managed locales.', null);
        //$this->addOption('backup-files', 'b', InputOption::VALUE_NONE, 'Makes a backup of yaml files updated.', null);
        //$this->addOption('force', 'f', InputOption::VALUE_NONE, 'Force import, replace database content.', null);
    }

    protected function init()
    {
        $this->srcDir     = realpath(ROOT_DIR) . '/';
    }


    /*
     * Scans folders to find translations files and extract catalog by filename
     */
    protected function getLocalCatalogs()
    {
        $result = array();

        $folders = array(
            $this->srcDir,
            dirname($this->srcDir) . '/app',
        );

        foreach($folders as $folder){
            $finder = new Finder();
            $finder->files()->in($folder)->name('/\w+\.\w+\.yml$/i');

            foreach($finder as $file){
                //$yml = $file->getRealpath();
                //$relativePath = $file->getRelativePath();
                $fileName = $file->getRelativePathname();

                if(preg_match("/translations\/(\w+)\.(\w+)\.yml/i", $fileName, $matches)){
                    $catalog = $matches[1];
                    $result[$catalog] = null;
                }
            }
        }

        return array_keys($result);
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input    = $input;
        $this->output   = $output;
        $this->init();

//        die('srcDir ' .$this->srcDir);
        $parameters = $this->getApplication()->config['parameters']['jlaso_translations_api_access'];
        //var_dump($parameters);
        $managedLocales = $parameters['managed_locales'];

        $this->output->writeln('<info>*** Extracting translating info from php and twig files ***</info>');

        $fileNames = array();
        $keys = array();
        $idx = 0;

        $patterns = array('*.twig', '*.php');
        $folder = ROOT_DIR . '/app';

        foreach($patterns as $pattern){
            $finder = new Finder();
            $files = $finder->in($folder)->name($pattern)->files();
            /** @var SplFileInfo[] $files */
            foreach($files as $file){
                $fileName = $folder . '/' . $file->getRelativePathname();
                if(strpos($file->getRelativePathname(), "cache/") === 0){

                }else{
                    $fileContents = file_get_contents($fileName);
                    if(preg_match_all("/_\((['\"])(?P<trans>[^\g{1}]*)\g{1}\)/iU", $fileContents, $matches)){
                        $fileNames[$idx] = $file->getRelativePathname();
                        //print $fileName . PHP_EOL;
                        //var_dump($matches["trans"]); die;
                        $keys[$idx] = $matches["trans"];
                    }
                    $idx++;
                }
            }
        }

        //var_dump($fileNames);
        //var_dump($keys);
        $result = array();

        foreach($fileNames as $index=>$fileName)
        {
            //$result[$fileName] = array();
            $this->output->writeln(sprintf("*** <info>%s</info> ***", $fileName));
            foreach($keys[$index] as $key){
                $tr = str_replace(
                    array(
                        "/"
                    ),
                    array(
                        "."
                    ),
                    trim(strtolower($fileName)
                    )
                );
                $tr = preg_replace("/\.php$/i", "", $tr);
                $tr = preg_replace("/\.html\.twig$/i", "", $tr);
                $k = $key; //html_entity_decode($key);
                $k = str_replace(
                    array("'","\'","%s","%d","&ntilde;","&Ntilde;"),
                    array("","","","","n","N"),
                    trim(strtolower($k))
                );
                $k = preg_replace("/[^\w]/", "_", $k);
                $k = str_replace("__","_", $k);
                $k = preg_replace("/^_/", "", $k);
                $k = preg_replace("/_$/", "", $k);
                $tr .= "." . $k;
                $this->output->writeln(sprintf("\t%s => %s", $key, $tr));
                $result[$tr] = $key;
            }
        }

        $this->save( __DIR__ . '/prueba.txt', $result);
        die;

        /*
        // adding a fake bundle to process translations from /app/Resources/translations
        $allBundles[self::APP_BUNDLE_KEY] = self::APP_BUNDLE_KEY;

        $catalogs = $this->getLocalCatalogs();

        // proccess local keys
        foreach ($allBundles as $bundleName)  {

            $this->output->writeln(PHP_EOL . sprintf("<error>%s</error>", $this->center($bundleName)));

            foreach($managedLocales as $locale){

                $this->output->writeln(PHP_EOL . sprintf('· %s/%s', $bundleName, $locale));

                foreach($catalogs as $catalog){
                    if(self::APP_BUNDLE_KEY == $bundleName){
                        $bundle = null;
                        $filePattern = $this->srcDir . '../app/Resources/translations/%s.%s.yml';
                    }else{
                        $bundle      = $this->getBundleByName($bundleName);
                        $filePattern = $bundle->getPath() . '/Resources/translations/%s.%s.yml';
                    }

                    $fileName = sprintf($filePattern, $catalog, $locale);

                    if(!file_exists($fileName)){
                        //$this->output->writeln(sprintf("· · <comment>File '%s' not found</comment>", $fileName));
                    }else{
                        //                    $maxDate = new \DateTime(date('c',filemtime($fileName)));
                        $hasChanged = false;
                        $localKeys  = $this->getYamlAsArray($fileName);
                        $this->output->writeln(sprintf("· · <info>Processing</info> '%s', found <info>%d</info> translations", $this->fileTrim($fileName), count($localKeys)));
                        //$this->output->writeln(sprintf("\t|-- <info>getKeys</info> informs that there are %d keys ", count($remoteKeys)));

                        foreach($localKeys as $localKey=>$message){

                            $this->output->writeln(sprintf("\t|-- key %s:%s/%s ... ", $bundleName, $localKey, $locale));
                            $this->updateOrInsertEntry($bundleName, $fileName, $localKey, $locale, $message, $catalog);
                        }

                    }

                    //unlink($fileName);
                    //$this->output->writeln('');
                }
            }
        }
        $this->em->flush();

        if ($this->input->getOption('cache-clear')) {
            $this->output->writeln(PHP_EOL . '<info>Removing translations cache files ...</info>');
            $this->getContainer()->get('translator')->removeLocalesCacheFiles($managedLocales);
        }

        $this->output->writeln('');
        */
    }

    protected function center($text, $width = 120)
    {
        $len = strlen($text);
        if($len<$width){
            $w = (intval($width - $len)/2);
            $left = str_repeat('·', $w);
            $right = str_repeat('·', $width - $len - $w);
            return  $left . $text . $right;
        }else{
            return $text;
        }
    }

    protected function fileTrim($fileName)
    {
        return str_replace(dirname($this->srcDir), '', $fileName);
    }

    /**
     * Dumps a message translations array to yaml file
     *
     * @param string $file
     * @param array $keys
     */
    protected function dumpYaml($file, $keys)
    {
        if($this->input->getOption('cache-clear') && file_exists($file)){
            // backups the file
            copy($file, $file . '.' . date('d-m-H-i'). '.bak');
        }
        if(!is_dir(dirname($file))){
            // the dir not exists
            mkdir(dirname($file), 0777, true);
        }
        $this->output->writeln(sprintf("\t|-- <info>saving file</info> '%s'", $this->fileTrim($file)));
        file_put_contents($file, Yaml::dump($this->k2a($keys), 100));
        //touch($fileName, $maxDate->format('U'));
    }


    /**
     * associative array indexed to dimensional associative array of keys
     *
     * @param $dest
     * @param $orig
     * @param $currentKey
     */
    protected function a2k(&$dest, $orig, $currentKey)
    {
        if(is_array($orig) && (count($orig)>0)){
            foreach($orig as $key=>$value){
                if(is_array($value)){
                    $this->a2k($dest, $value, ($currentKey ? $currentKey . '.' : '') . $key);
                }else{
                    $dest[($currentKey ? $currentKey . '.' : '') . $key] = $value;
                    //$tmp = explode('.', $currentKey);
                    //$currentKey = implode('.', array_pop($tmp));
                }
            }
        }
    }

    /**
     * Reads a Yaml file and process the keys and returns as a associative indexed array
     *
     * @param string $file
     *
     * @return array
     */
    protected function getYamlAsArray($file)
    {
        if(file_exists($file)){
            $content = Yaml::parse(file_get_contents($file));
            $result  = array();
            $this->a2k($result, $content, '');

            return $result;
        }else{
            return array();
        }
    }

    /**
     * dimensional associative array of keys to associative array indexed
     *
     * @param $orig
     *
     * @return array
     */
    protected function k2a($orig)
    {
        $result = array();
        foreach($orig as $key=>$value){
            if($value===null){

            }else{
                $keys = explode('.',$key);
                $node = $value;
                for($i = count($keys); $i>0; $i--){
                    $k = $keys[$i-1];
                    $node = array($k => $node);
                }
                $result = array_merge_recursive($result, $node);
            }
        }

        return $result;
    }

    /**
     * @param string $message
     * @throws \Exception
     */
    protected function throwException($message)
    {
        $message = $message ?: 'Unexpected exception';
        //print $message;
        throw new \Exception($message);
    }

    /**
     * Scans given files or directories and extracts gettext keys from the content
     * @param string|array $resource
     * @return GettetExtractor
     */
    /*public function scan($resource)
    {
        $this->inputFiles = array();
        if (!is_array($resource)) $resource = array($resource);
        foreach ($resource as $item) {
            $this->log("Scanning '$item'");
            $this->_scan($item);
        }
        $this->_extract($this->inputFiles);
        return $this;
    }*/

    /**
     * Scans given files or directories (recursively) and stores extracted gettext keys in a buffer
     * @param string $resource File or directory
     */
    protected function extract($resource)
    {
        /*if (!is_dir($resource) && !is_file($resource)) {
            $this->throwException("Resource '$resource' is not a directory or file");
        }

        if (is_file($resource)) {
            $this->inputFiles[] = realpath($resource);
            return;
        }*/

        // It's a directory
        $resource = realpath($resource);
        if (!$resource) return;
        $iterator = dir($resource);
        if (!$iterator) return;

        while (FALSE !== ($entry = $iterator->read())) {
            if ($entry == '.' || $entry == '..') continue;
            if ($entry[0] == '.') continue; // do not browse into .git directories

            $path = $resource . '/' . $entry;
            if (!is_readable($path)) continue;

            if (is_dir($path)) {
                $this->_scan($path);
                continue;
            }

            if (is_file($path)) {
                $info = pathinfo($path);
                if (!isset($info['extension'])) continue; // "lockfile" has no extension.. raises notice
                if (!isset($this->filters[$info['extension']])) continue;
                $this->inputFiles[] = realpath($path);
            }
        }

        $iterator->close();

    }

    /**
     * Extracts gettext keys from input files
     * @param array $inputFiles
     * @return array
     */
    protected function _extract($inputFiles)
    {
        $inputFiles = array_unique($inputFiles);
        foreach ($inputFiles as $inputFile)
        {
            if (!file_exists($inputFile)) {
                $this->throwException('ERROR: Invalid input file specified: ' . $inputFile);
            }
            if (!is_readable($inputFile)) {
                $this->throwException('ERROR: Input file is not readable: ' . $inputFile);
            }

            $this->log('Extracting data from file ' . $inputFile);
            foreach ($this->filters as $extension => $filters)
            {
                // Check file extension
                $info = pathinfo($inputFile);
                if ($info['extension'] !== $extension) continue;

                $this->log('Processing file ' . $inputFile);

                foreach ($filters as $filterName)
                {
                    $filter = $this->getFilter($filterName);
                    $filterData = $filter->extract($inputFile);
                    $this->log('  Filter ' . $filterName . ' applied');
                    $this->data = array_merge_recursive($this->data, $filterData);
                }
            }
        }
        return $this->data;
    }

    /**
     * Gets an instance of a GettextExtractor filter
     * @param string $filter
     * @return iFilter
     */
    public function getFilter($filter)
    {
        $filter = $filter . 'Filter';

        if (isset($this->filterStore[$filter])) return $this->filterStore[$filter];

        if (!class_exists($filter)) {
            $filter_file = dirname(__FILE__) . '/Filters/' . $filter . ".php";
            if (!file_exists($filter_file)) {
                $this->throwException('ERROR: Filter file ' . $filter_file . ' not found');
            }
            require_once $filter_file;
            if (!class_exists($filter)) {
                $this->throwException('ERROR: Class ' . $filter . ' not found');
            }
        }

        $this->filterStore[$filter] = new $filter;
        $this->log('Filter ' . $filter . ' loaded');
        return $this->filterStore[$filter];
    }

    /**
     * Assigns a filter to an extension
     * @param string $extension
     * @param string $filter
     * @return GettextExtractor
     */
    public function setFilter($extension, $filter)
    {
        if (isset($this->filters[$extension]) && in_array($filter, $this->filters[$extension])) return $this;
        $this->filters[$extension][] = $filter;
        return $this;
    }

    /**
     * Removes all filter settings in case we want to define a brand new one
     * @return GettextExtractor
     */
    public function removeAllFilters()
    {
        $this->filters = array();
        return $this;
    }

    /**
     * Adds a comment to the top of the output file
     * @param string $value
     * @return GettextExtractor
     */
    public function addComment($value) {
        $this->comments[] = $value;
        return $this;
    }

    /**
     * Gets a value of a meta key
     * @param string $key
     */
    public function getMeta($key)
    {
        return isset($this->meta[$key]) ? $this->meta[$key] : NULL;
    }

    /**
     * Sets a value of a meta key
     * @param string $key
     * @param string $value
     * @return GettextExtractor
     */
    public function setMeta($key, $value)
    {
        $this->meta[$key] = $value;
        return $this;
    }

    /**
     * Saves extracted data into gettext file
     * @param string $outputFile
     * @param array $data
     * @return GettextExtractor
     */
    public function save($outputFile, $data)
    {
        if (file_exists($outputFile) && !is_writable($outputFile)) {
            $this->throwException('ERROR: Output file is not writable!');
        }

        $handle = fopen($outputFile, "w");

        fwrite($handle, $this->formatData($data));

        fclose($handle);

        //$this->log("Output file '$outputFile' created");

        return $this;
    }

    /**
     * Formats fetched data to gettext syntax
     * @param array $data
     * @return string
     */
    protected function formatData($data)
    {
        $output = array();
        foreach ($this->comments as $comment) {
            $output[] = '# ' . $comment;
        }
        $output[] = '# Created: ' . date('c');
        $output[] = 'msgid ""';
        $output[] = 'msgstr ""';
        foreach ($this->meta as $key => $value) {
            $output[] = '"' . $key . ': ' . $value . '\n"';
        }
        $output[] = '';

        ksort($data);

        foreach ($data as $key => $msg)
        {
            $output[] = '# ' . $msg;
            $output[] = 'msgid "' . $this->addSlashes($key) . '"';
            $output[] = 'msgstr "' . $this->addSlashes($msg) . '"';
            $output[] = '';
        }

        return join("\n", $output);
    }

    /**
     * Escape a sring not to break the gettext syntax
     * @param string $string
     * @return string
     */
    public function addSlashes($string)
    {
        return addcslashes($string, self::ESCAPE_CHARS);
    }


}
