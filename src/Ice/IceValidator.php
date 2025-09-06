<?php

namespace Lynnezra\Seat\Connector\Drivers\Mumble\Ice;

use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Ice 接口配置验证器
 * 
 * 用于验证 Ice 接口的配置参数和环境要求
 */
class IceValidator
{
    /**
     * 验证 Ice 环境
     * 
     * @return array 验证结果
     */
    public static function validateEnvironment(): array
    {
        $results = [
            'ice_extension' => self::checkIceExtension(),
            'ice_version' => self::getIceVersion(),
            'php_version' => self::checkPhpVersion(),
            'required_classes' => self::checkRequiredClasses(),
            'overall_status' => true,
            'errors' => [],
            'warnings' => []
        ];
        
        // 检查是否有任何错误
        foreach ($results as $key => $result) {
            if ($key !== 'overall_status' && $key !== 'errors' && $key !== 'warnings') {
                if (is_array($result) && isset($result['status']) && !$result['status']) {
                    $results['overall_status'] = false;
                    $results['errors'][] = $result['message'] ?? "Check failed: {$key}";
                }
            }
        }
        
        return $results;
    }
    
    /**
     * 验证 Ice 配置
     * 
     * @param object $settings 配置对象
     * @return array 验证结果
     */
    public static function validateConfiguration($settings): array
    {
        $results = [
            'host_config' => self::validateHost($settings),
            'port_config' => self::validatePort($settings),
            'secret_config' => self::validateSecret($settings),
            'timeout_config' => self::validateTimeout($settings),
            'connectivity' => self::testConnectivity($settings),
            'overall_status' => true,
            'errors' => [],
            'warnings' => []
        ];
        
        // 收集错误和警告
        foreach ($results as $key => $result) {
            if (is_array($result) && isset($result['status'])) {
                if (!$result['status']) {
                    $results['overall_status'] = false;
                    $results['errors'][] = $result['message'] ?? "Configuration error: {$key}";
                } elseif (isset($result['warning'])) {
                    $results['warnings'][] = $result['warning'];
                }
            }
        }
        
        return $results;
    }
    
    /**
     * 检查 Ice 扩展是否加载
     */
    private static function checkIceExtension(): array
    {
        $loaded = extension_loaded('ice');
        
        return [
            'status' => $loaded,
            'message' => $loaded ? 'Ice extension is loaded' : 'Ice extension not found. Please install php-zeroc-ice.',
            'extension_loaded' => $loaded
        ];
    }
    
    /**
     * 获取 Ice 版本信息
     */
    private static function getIceVersion(): array
    {
        if (!extension_loaded('ice')) {
            return [
                'status' => false,
                'message' => 'Ice extension not loaded',
                'version' => null
            ];
        }
        
        try {
            $version = phpversion('ice');
            return [
                'status' => true,
                'message' => "Ice extension version: {$version}",
                'version' => $version
            ];
        } catch (Exception $e) {
            return [
                'status' => false,
                'message' => 'Unable to determine Ice version',
                'version' => null
            ];
        }
    }
    
    /**
     * 检查 PHP 版本兼容性
     */
    private static function checkPhpVersion(): array
    {
        $phpVersion = PHP_VERSION;
        $minVersion = '7.4.0';
        
        $compatible = version_compare($phpVersion, $minVersion, '>=');
        
        return [
            'status' => $compatible,
            'message' => $compatible 
                ? "PHP version {$phpVersion} is compatible" 
                : "PHP version {$phpVersion} is too old. Minimum required: {$minVersion}",
            'current_version' => $phpVersion,
            'min_version' => $minVersion
        ];
    }
    
    /**
     * 检查必需的 Ice 类是否可用
     */
    private static function checkRequiredClasses(): array
    {
        $requiredClasses = [
            'Ice\Communicator',
            'Ice\Properties',
            'Ice\InitializationData'
        ];
        
        $missing = [];
        
        foreach ($requiredClasses as $class) {
            if (!class_exists($class)) {
                $missing[] = $class;
            }
        }
        
        $allPresent = empty($missing);
        
        return [
            'status' => $allPresent,
            'message' => $allPresent 
                ? 'All required Ice classes are available' 
                : 'Missing Ice classes: ' . implode(', ', $missing),
            'missing_classes' => $missing
        ];
    }
    
    /**
     * 验证主机配置
     */
    private static function validateHost($settings): array
    {
        $host = $settings->mumble_ice_host ?? null;
        
        if (empty($host)) {
            return [
                'status' => false,
                'message' => 'Ice host not configured'
            ];
        }
        
        // 验证主机格式
        if (!filter_var($host, FILTER_VALIDATE_IP) && !filter_var($host, FILTER_VALIDATE_DOMAIN)) {
            return [
                'status' => false,
                'message' => "Invalid host format: {$host}"
            ];
        }
        
        return [
            'status' => true,
            'message' => "Host configured: {$host}",
            'host' => $host
        ];
    }
    
    /**
     * 验证端口配置
     */
    private static function validatePort($settings): array
    {
        $port = $settings->mumble_ice_port ?? null;
        
        if (is_null($port)) {
            return [
                'status' => true,
                'message' => 'Using default Ice port: 6502',
                'port' => 6502,
                'warning' => 'Ice port not explicitly configured, using default'
            ];
        }
        
        if (!is_numeric($port) || $port <= 0 || $port > 65535) {
            return [
                'status' => false,
                'message' => "Invalid port: {$port}. Must be between 1 and 65535."
            ];
        }
        
        return [
            'status' => true,
            'message' => "Port configured: {$port}",
            'port' => (int)$port
        ];
    }
    
    /**
     * 验证密钥配置
     */
    private static function validateSecret($settings): array
    {
        $secret = $settings->mumble_ice_secret ?? null;
        
        if (empty($secret)) {
            return [
                'status' => true,
                'message' => 'No Ice secret configured (authentication disabled)',
                'warning' => 'Ice secret not configured. This may be a security risk in production.'
            ];
        }
        
        if (strlen($secret) < 8) {
            return [
                'status' => true,
                'message' => 'Ice secret configured but appears weak',
                'warning' => 'Ice secret is shorter than 8 characters. Consider using a stronger secret.'
            ];
        }
        
        return [
            'status' => true,
            'message' => 'Ice secret configured',
            'secret_length' => strlen($secret)
        ];
    }
    
    /**
     * 验证超时配置
     */
    private static function validateTimeout($settings): array
    {
        $timeout = $settings->mumble_ice_timeout ?? null;
        
        if (is_null($timeout)) {
            return [
                'status' => true,
                'message' => 'Using default timeout: 10 seconds',
                'timeout' => 10,
                'warning' => 'Ice timeout not configured, using default'
            ];
        }
        
        if (!is_numeric($timeout) || $timeout <= 0) {
            return [
                'status' => false,
                'message' => "Invalid timeout: {$timeout}. Must be a positive number."
            ];
        }
        
        if ($timeout > 60) {
            return [
                'status' => true,
                'message' => "Timeout configured: {$timeout}s",
                'timeout' => (int)$timeout,
                'warning' => 'Timeout is quite long. Consider reducing it for better responsiveness.'
            ];
        }
        
        return [
            'status' => true,
            'message' => "Timeout configured: {$timeout}s",
            'timeout' => (int)$timeout
        ];
    }
    
    /**
     * 测试连接性
     */
    private static function testConnectivity($settings): array
    {
        if (!extension_loaded('ice')) {
            return [
                'status' => false,
                'message' => 'Cannot test connectivity: Ice extension not loaded'
            ];
        }
        
        $host = $settings->mumble_ice_host ?? '127.0.0.1';
        $port = $settings->mumble_ice_port ?? 6502;
        
        // 简单的 TCP 连接测试
        $socket = @fsockopen($host, $port, $errno, $errstr, 5);
        
        if ($socket) {
            fclose($socket);
            return [
                'status' => true,
                'message' => "Successfully connected to {$host}:{$port}"
            ];
        }
        
        return [
            'status' => false,
            'message' => "Cannot connect to {$host}:{$port} - {$errstr} ({$errno})"
        ];
    }
    
    /**
     * 生成配置报告
     */
    public static function generateReport($settings): string
    {
        $envCheck = self::validateEnvironment();
        $configCheck = self::validateConfiguration($settings);
        
        $report = "=== Mumble Ice Interface Configuration Report ===\n\n";
        
        // 环境检查
        $report .= "Environment Check:\n";
        $report .= "- PHP Version: " . PHP_VERSION . "\n";
        $report .= "- Ice Extension: " . ($envCheck['ice_extension']['status'] ? 'LOADED' : 'NOT LOADED') . "\n";
        
        if ($envCheck['ice_version']['version']) {
            $report .= "- Ice Version: " . $envCheck['ice_version']['version'] . "\n";
        }
        
        $report .= "- Overall Environment Status: " . ($envCheck['overall_status'] ? 'OK' : 'FAILED') . "\n\n";
        
        // 配置检查
        $report .= "Configuration Check:\n";
        $report .= "- Host: " . ($settings->mumble_ice_host ?? 'not set') . "\n";
        $report .= "- Port: " . ($settings->mumble_ice_port ?? 'not set') . "\n";
        $report .= "- Secret: " . (empty($settings->mumble_ice_secret) ? 'not set' : 'configured') . "\n";
        $report .= "- Timeout: " . ($settings->mumble_ice_timeout ?? 'not set') . "\n";
        $report .= "- Overall Configuration Status: " . ($configCheck['overall_status'] ? 'OK' : 'FAILED') . "\n\n";
        
        // 错误和警告
        if (!empty($envCheck['errors']) || !empty($configCheck['errors'])) {
            $report .= "ERRORS:\n";
            foreach (array_merge($envCheck['errors'], $configCheck['errors']) as $error) {
                $report .= "- {$error}\n";
            }
            $report .= "\n";
        }
        
        if (!empty($envCheck['warnings']) || !empty($configCheck['warnings'])) {
            $report .= "WARNINGS:\n";
            foreach (array_merge($envCheck['warnings'], $configCheck['warnings']) as $warning) {
                $report .= "- {$warning}\n";
            }
            $report .= "\n";
        }
        
        $report .= "=== End of Report ===\n";
        
        return $report;
    }
}