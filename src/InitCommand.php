<?php

// Originally forked from the Laravel Installer, MIT license, by Taylor Otwell
// https://github.com/laravel/installer

namespace Prismic\Installer\Console;

use ZipArchive;
use RuntimeException;
use GuzzleHttp\Client;
use Symfony\Component\Process\Process;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

if (!function_exists('glob_recursive'))
{
    // Does not support flag GLOB_BRACE
    function glob_recursive($pattern, $flags = 0)
    {
        $files = glob($pattern, $flags);

        foreach (glob(dirname($pattern).'/*', GLOB_ONLYDIR|GLOB_NOSORT) as $dir)
        {
            $files = array_merge($files, glob_recursive($dir.'/'.basename($pattern), $flags));
        }

        return $files;
    }
}

class InitCommand extends Command
{
    /**
     * Configure the command options.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('init')
            ->setDescription('Create a new Prismic.io application.')
            ->addArgument('repository', InputArgument::REQUIRED)
            ->addOption('template', 't', InputOption::VALUE_OPTIONAL, 'Project template')
            ->addOption('folder', 'f', InputOption::VALUE_OPTIONAL, 'Folder to create the project');
    }

    /**
     * Execute the command.
     *
     * @param  InputInterface  $input
     * @param  OutputInterface  $output
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (! class_exists('ZipArchive')) {
            throw new RuntimeException('The Zip PHP extension is not installed. Please install it and try again.');
        }

        $repo = $input->getArgument('repository');

        $this->verifyApplicationDoesntExist(
            $directory = ($input->getOption('folder')) ? getcwd().'/'.$input->getOption('folder') : getcwd().'/'.$repo,
            $output
        );

        $output->writeln('<info>Crafting application...</info>');

        $template = $this->getTemplate($input);

        $this->download($zipFile = $this->makeFilename(), $template)
             ->extract($zipFile, $directory, $template)
             ->findAndReplace(glob_recursive($directory.'/*'), '/your-repo-name/', $repo)
             ->cleanUp($zipFile);

        $composer = $this->findComposer();

        $commands = [
            $composer.' install --no-scripts'
        ];

        if ($input->getOption('no-ansi')) {
            $commands = array_map(function ($value) {
                return $value.' --no-ansi';
            }, $commands);
        }

        $process = new Process(implode(' && ', $commands), $directory, null, null, null);

        if ('\\' !== DIRECTORY_SEPARATOR && file_exists('/dev/tty') && is_readable('/dev/tty')) {
            $process->setTty(true);
        }

        $process->run(function ($type, $line) use ($output) {
            $output->write($line);
        });

        $output->writeln('<comment>Application ready!</comment>');
    }

    /**
     * Verify that the application does not already exist.
     *
     * @param  string  $directory
     * @return void
     */
    protected function verifyApplicationDoesntExist($directory, OutputInterface $output)
    {
        if ((is_dir($directory) || is_file($directory)) && $directory != getcwd()) {
            throw new RuntimeException('Folder ' . $directory . ' already exists!');
        }
    }

    /**
     * Generate a random temporary filename.
     *
     * @return string
     */
    protected function makeFilename()
    {
        return getcwd().'/prismic_'.md5(time().uniqid()).'.zip';
    }

    /**
     * Download the temporary Zip to the given file.
     *
     * @param  string  $zipFile
     * @param  string  $template
     * @return $this
     */
    protected function download($zipFile, $template)
    {
        $response = (new Client)->get($template['url']);
        file_put_contents($zipFile, $response->getBody());
        return $this;
    }

    /**
     * Extract the zip file into the given directory.
     *
     * @param  string  $zipFile
     * @param  string  $directory
     * @return $this
     */
    protected function extract($zipFile, $directory, $template)
    {
        $archive = new ZipArchive;
        $archive->open($zipFile);
        $archive->extractTo(getcwd());
        $archive->close();
        rename($template['inner'], $directory);
        return $this;
    }

    /**
     * Clean-up the Zip file.
     *
     * @param  string  $zipFile
     * @return $this
     */
    protected function cleanUp($zipFile)
    {
        @chmod($zipFile, 0777);
        @unlink($zipFile);
        return $this;
    }

    /**
     * Search-and-replace to inject repository name in the source
     */
    protected function findAndReplace($files, $source, $dest) {
        foreach ($files as $filename)
        {
            if (!is_dir($filename)) {
                $file = file_get_contents($filename);
                file_put_contents($filename, preg_replace($source, $dest, $file));
            }
        }
        return $this;
    }


    /**
     * Get the version that should be downloaded.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @return string
     */
    protected function getTemplate($input)
    {
        if ($input->getOption('template')) {
            $name = $input->getOption('template');
            if (array_key_exists($name, PRISMIC_TEMPLATES)) {
                return PRISMIC_TEMPLATES[$name];
            } else {
                throw new RuntimeException('Unkown template: ' . $name);
            }
        }

        return array_values(PRISMIC_TEMPLATES)[0];
    }

    /**
     * Get the composer command for the environment.
     *
     * @return string
     */
    protected function findComposer()
    {
        if (file_exists(getcwd().'/composer.phar')) {
            return '"'.PHP_BINARY.'" composer.phar';
        }
        return 'composer';
    }
}
