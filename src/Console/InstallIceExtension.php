<?php

namespace Lynnezra\Seat\Connector\Drivers\Mumble\Console;

use Illuminate\Console\Command;

/**
 * Ice 扩展安装助手
 * 
 * 提供自动检测和安装指导
 */
class InstallIceExtension extends Command
{
    protected $signature = 'mumble:install-ice 
                            {--check : 仅检查Ice扩展状态}
                            {--guide : 显示详细安装指南}
                            {--auto : 尝试自动安装（需要sudo权限）}';

    protected $description = 'Ice extension installation helper';

    public function handle(): int
    {
        $this->info('=== Mumble Ice Extension Installation Helper ===');
        $this->newLine();

        // 1. 检查当前状态
        $this->checkCurrentStatus();

        if ($this->option('check')) {
            return 0;
        }

        // 2. 显示安装指南
        if ($this->option('guide')) {
            $this->showInstallationGuide();
            return 0;
        }

        // 3. 尝试自动安装
        if ($this->option('auto')) {
            return $this->attemptAutoInstall();
        }

        // 4. 默认显示快速指南
        $this->showQuickGuide();
        return 0;
    }

    /**
     * 检查当前状态
     */
    private function checkCurrentStatus(): void
    {
        $this->info('1. Checking current status...');

        // 检查 PHP 版本
        $phpVersion = PHP_VERSION;
        $this->line("   PHP Version: {$phpVersion}");

        // 检查 Ice 扩展
        $iceLoaded = extension_loaded('ice');
        if ($iceLoaded) {
            $iceVersion = phpversion('ice');
            $this->info("   ✅ Ice Extension: Loaded (Version: {$iceVersion})");
            $this->info("   🎉 Ice extension is already installed and ready!");
            return;
        } else {
            $this->error("   ❌ Ice Extension: Not loaded");
        }

        // 检查操作系统
        $os = $this->detectOS();
        $this->line("   Operating System: {$os}");

        // 检查包管理器
        $packageManager = $this->detectPackageManager();
        $this->line("   Package Manager: {$packageManager}");

        // 检查编译工具
        $this->checkBuildTools();
    }

    /**
     * 检测操作系统
     */
    private function detectOS(): string
    {
        if (stripos(PHP_OS, 'WIN') === 0) {
            return 'Windows';
        } elseif (stripos(PHP_OS, 'DARWIN') === 0) {
            return 'macOS';
        } elseif (file_exists('/etc/os-release')) {
            $osRelease = file_get_contents('/etc/os-release');
            if (strpos($osRelease, 'Ubuntu') !== false) {
                return 'Ubuntu';
            } elseif (strpos($osRelease, 'Debian') !== false) {
                return 'Debian';
            } elseif (strpos($osRelease, 'CentOS') !== false) {
                return 'CentOS';
            } elseif (strpos($osRelease, 'Red Hat') !== false) {
                return 'RHEL';
            }
        }
        return 'Linux (Unknown Distribution)';
    }

    /**
     * 检测包管理器
     */
    private function detectPackageManager(): string
    {
        $managers = [
            'apt-get' => 'APT (Debian/Ubuntu)',
            'yum' => 'YUM (CentOS/RHEL)',
            'dnf' => 'DNF (Fedora/CentOS 8+)',
            'pacman' => 'Pacman (Arch)',
            'zypper' => 'Zypper (openSUSE)',
        ];

        foreach ($managers as $cmd => $name) {
            if ($this->commandExists($cmd)) {
                return $name;
            }
        }

        return 'Unknown';
    }

    /**
     * 检查编译工具
     */
    private function checkBuildTools(): void
    {
        $tools = ['gcc', 'make', 'phpize'];
        $available = [];
        $missing = [];

        foreach ($tools as $tool) {
            if ($this->commandExists($tool)) {
                $available[] = $tool;
            } else {
                $missing[] = $tool;
            }
        }

        if (!empty($available)) {
            $this->line("   Build Tools Available: " . implode(', ', $available));
        }

        if (!empty($missing)) {
            $this->warn("   Build Tools Missing: " . implode(', ', $missing));
        }
    }

    /**
     * 检查命令是否存在
     */
    private function commandExists(string $command): bool
    {
        $return = shell_exec("which {$command} 2>/dev/null");
        return !empty($return);
    }

    /**
     * 显示快速安装指南
     */
    private function showQuickGuide(): void
    {
        $this->newLine();
        $this->info('2. Quick Installation Guide:');

        $os = $this->detectOS();

        switch (true) {
            case strpos($os, 'Ubuntu') !== false:
            case strpos($os, 'Debian') !== false:
                $this->showUbuntuGuide();
                break;
            case strpos($os, 'CentOS') !== false:
            case strpos($os, 'RHEL') !== false:
                $this->showCentOSGuide();
                break;
            case strpos($os, 'Windows') !== false:
                $this->showWindowsGuide();
                break;
            case strpos($os, 'macOS') !== false:
                $this->showMacOSGuide();
                break;
            default:
                $this->showGenericGuide();
                break;
        }

        $this->newLine();
        $this->info('3. After installation, run: php artisan mumble:install-ice --check');
    }

    /**
     * Ubuntu/Debian 安装指南
     */
    private function showUbuntuGuide(): void
    {
        $this->line('   Ubuntu/Debian Installation:');
        $this->line('   
   # Method 1: Using package manager (recommended)
   sudo apt-get update
   sudo apt-get install php-zeroc-ice

   # Method 2: Manual compilation
   sudo apt-get install libzeroc-ice-dev ice-slice build-essential
   sudo apt-get install php-dev  # PHP development headers
   
   # Download and compile Ice PHP extension
   wget https://download.zeroc.com/Ice/3.7/php-ice-3.7.5.tar.gz
   tar -xzf php-ice-3.7.5.tar.gz
   cd php-ice-3.7.5
   phpize
   ./configure
   make
   sudo make install
   
   # Enable extension
   echo "extension=ice.so" | sudo tee -a /etc/php/$(php -r "echo PHP_MAJOR_VERSION.\\".\\".PHP_MINOR_VERSION;")/cli/php.ini
   echo "extension=ice.so" | sudo tee -a /etc/php/$(php -r "echo PHP_MAJOR_VERSION.\\".\\".PHP_MINOR_VERSION;")/fpm/php.ini
   
   # Restart services
   sudo systemctl restart php$(php -r "echo PHP_MAJOR_VERSION.\\".\\".PHP_MINOR_VERSION;")-fpm
   sudo systemctl restart nginx  # or apache2
        ');
    }

    /**
     * CentOS/RHEL 安装指南
     */
    private function showCentOSGuide(): void
    {
        $this->line('   CentOS/RHEL Installation:');
        $this->line('   
   # Install EPEL repository
   sudo yum install epel-release
   
   # Install Ice
   sudo yum install ice ice-php ice-devel
   
   # Or for newer systems
   sudo dnf install ice ice-php ice-devel
   
   # Enable extension (if not auto-enabled)
   echo "extension=ice.so" | sudo tee -a /etc/php.ini
   
   # Restart services
   sudo systemctl restart php-fpm
   sudo systemctl restart httpd  # or nginx
        ');
    }

    /**
     * Windows 安装指南
     */
    private function showWindowsGuide(): void
    {
        $this->line('   Windows Installation:');
        $this->line('   
   Windows installation is more complex and requires:
   
   1. Download ZeroC Ice for Windows from:
      https://zeroc.com/downloads/ice
   
   2. Install Visual Studio Build Tools
   
   3. Compile PHP Ice extension manually
   
   4. Add extension=ice.dll to php.ini
   
   For easier setup, consider using Docker:
   docker run -it --name mumble-dev php:8.1-cli bash
        ');
    }

    /**
     * macOS 安装指南
     */
    private function showMacOSGuide(): void
    {
        $this->line('   macOS Installation:');
        $this->line('   
   # Using Homebrew
   brew install ice
   
   # Install PHP development tools
   brew install php
   
   # Manual compilation may be required
   # Follow the Linux compilation steps
        ');
    }

    /**
     * 通用安装指南
     */
    private function showGenericGuide(): void
    {
        $this->line('   Generic Linux Installation:');
        $this->line('   
   1. Install ZeroC Ice development package
   2. Install PHP development headers
   3. Download Ice PHP extension source
   4. Compile: phpize && ./configure && make && sudo make install
   5. Add extension=ice.so to php.ini
   6. Restart web server
        ');
    }

    /**
     * 显示详细安装指南
     */
    private function showInstallationGuide(): void
    {
        $this->info('=== Detailed Installation Guide ===');
        
        $this->newLine();
        $this->info('Prerequisites:');
        $this->line('• Root or sudo access');
        $this->line('• Internet connection');
        $this->line('• PHP development headers');
        $this->line('• Build tools (gcc, make, etc.)');
        
        $this->newLine();
        $this->showQuickGuide();
        
        $this->newLine();
        $this->info('Docker Alternative:');
        $this->line('   
   If installation is too complex, use Docker:
   
   # Create Dockerfile with Ice support
   FROM php:8.1-fpm
   RUN apt-get update && apt-get install -y php-zeroc-ice
   
   # Or use pre-built image (if available)
   docker pull your-registry/php-ice:8.1
        ');
        
        $this->newLine();
        $this->info('Verification:');
        $this->line('   php -m | grep ice');
        $this->line('   php artisan mumble:install-ice --check');
    }

    /**
     * 尝试自动安装
     */
    private function attemptAutoInstall(): int
    {
        $this->warn('⚠️  Attempting automatic installation (requires sudo)...');
        
        if (!$this->confirm('This will run system commands with sudo. Continue?')) {
            $this->info('Installation cancelled.');
            return 1;
        }

        $os = $this->detectOS();
        
        try {
            switch (true) {
                case strpos($os, 'Ubuntu') !== false:
                case strpos($os, 'Debian') !== false:
                    return $this->autoInstallUbuntu();
                case strpos($os, 'CentOS') !== false:
                case strpos($os, 'RHEL') !== false:
                    return $this->autoInstallCentOS();
                default:
                    $this->error('Automatic installation not supported for this OS.');
                    $this->info('Please use manual installation.');
                    return 1;
            }
        } catch (\Exception $e) {
            $this->error('Automatic installation failed: ' . $e->getMessage());
            $this->info('Please try manual installation.');
            return 1;
        }
    }

    /**
     * Ubuntu 自动安装
     */
    private function autoInstallUbuntu(): int
    {
        $this->info('Installing Ice extension on Ubuntu/Debian...');
        
        $commands = [
            'sudo apt-get update',
            'sudo apt-get install -y php-zeroc-ice',
        ];

        foreach ($commands as $command) {
            $this->line("Running: {$command}");
            $output = shell_exec($command . ' 2>&1');
            
            if (strpos($output, 'E:') !== false || strpos($output, 'error') !== false) {
                $this->error("Command failed: {$command}");
                $this->line($output);
                return 1;
            }
        }

        // 重启服务
        $phpVersion = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
        shell_exec("sudo systemctl restart php{$phpVersion}-fpm 2>/dev/null");
        
        // 验证安装
        if (extension_loaded('ice')) {
            $this->info('✅ Ice extension installed successfully!');
            return 0;
        } else {
            $this->warn('Installation completed but extension not loaded. You may need to restart your web server.');
            return 0;
        }
    }

    /**
     * CentOS 自动安装
     */
    private function autoInstallCentOS(): int
    {
        $this->info('Installing Ice extension on CentOS/RHEL...');
        
        $commands = [
            'sudo yum install -y epel-release',
            'sudo yum install -y ice ice-php ice-devel',
        ];

        foreach ($commands as $command) {
            $this->line("Running: {$command}");
            $output = shell_exec($command . ' 2>&1');
            
            if (strpos($output, 'Error') !== false) {
                $this->error("Command failed: {$command}");
                $this->line($output);
                return 1;
            }
        }

        // 重启服务
        shell_exec('sudo systemctl restart php-fpm 2>/dev/null');
        
        // 验证安装
        if (extension_loaded('ice')) {
            $this->info('✅ Ice extension installed successfully!');
            return 0;
        } else {
            $this->warn('Installation completed but extension not loaded. You may need to restart your web server.');
            return 0;
        }
    }
}