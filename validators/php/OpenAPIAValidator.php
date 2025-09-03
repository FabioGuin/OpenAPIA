<?php

/**
 * OpenAPIA Validator - PHP Implementation
 * 
 * A comprehensive validator for OpenAPIA specifications.
 * 
 * @package OpenAPIA
 * @version 0.1.0
 * @license Apache-2.0
 */

namespace OpenAPIA;

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

class OpenAPIAValidator
{
    private array $errors = [];
    private array $warnings = [];
    private string $schemaVersion = '0.1.0';
    
    /**
     * Validate an OpenAPIA specification file
     */
    public function validateFile(string $filePath): bool
    {
        try {
            if (!file_exists($filePath)) {
                $this->errors[] = "File not found: {$filePath}";
                return false;
            }
            
            $content = file_get_contents($filePath);
            if ($content === false) {
                $this->errors[] = "Cannot read file: {$filePath}";
                return false;
            }
            
            $spec = $this->parseFile($filePath, $content);
            if ($spec === null) {
                return false;
            }
            
            return $this->validateSpec($spec);
            
        } catch (\Exception $e) {
            $this->errors[] = "Unexpected error: " . $e->getMessage();
            return false;
        }
    }
    
    /**
     * Parse file content based on file extension
     */
    private function parseFile(string $filePath, string $content): ?array
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        
        switch ($extension) {
            case 'yaml':
            case 'yml':
                try {
                    return Yaml::parse($content);
                } catch (ParseException $e) {
                    $this->errors[] = "YAML parsing error: " . $e->getMessage();
                    return null;
                }
                
            case 'json':
                $decoded = json_decode($content, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $this->errors[] = "JSON parsing error: " . json_last_error_msg();
                    return null;
                }
                return $decoded;
                
            default:
                $this->errors[] = "Unsupported file format: {$extension}";
                return null;
        }
    }
    
    /**
     * Validate an OpenAPIA specification array
     */
    public function validateSpec(array $spec): bool
    {
        $this->errors = [];
        $this->warnings = [];
        
        // Validate required sections
        $this->validateRequiredSections($spec);
        
        // Validate each section
        if (isset($spec['openapia'])) {
            $this->validateOpenAPIAVersion($spec['openapia']);
        }
        
        if (isset($spec['info'])) {
            $this->validateInfo($spec['info']);
        }
        
        if (isset($spec['models'])) {
            $this->validateModels($spec['models']);
        }
        
        if (isset($spec['prompts'])) {
            $this->validatePrompts($spec['prompts']);
        }
        
        if (isset($spec['constraints'])) {
            $this->validateConstraints($spec['constraints']);
        }
        
        if (isset($spec['tasks'])) {
            $this->validateTasks($spec['tasks']);
        }
        
        if (isset($spec['context'])) {
            $this->validateContext($spec['context']);
        }
        
        if (isset($spec['evaluation'])) {
            $this->validateEvaluation($spec['evaluation']);
        }
        
        // Cross-validation
        $this->crossValidate($spec);
        
        return empty($this->errors);
    }
    
    /**
     * Validate that all required sections are present
     */
    private function validateRequiredSections(array $spec): void
    {
        $requiredSections = [
            'openapia', 'info', 'models', 'prompts',
            'constraints', 'tasks', 'context', 'evaluation'
        ];
        
        foreach ($requiredSections as $section) {
            if (!array_key_exists($section, $spec)) {
                $this->errors[] = "Missing required section: {$section}";
            }
        }
    }
    
    /**
     * Validate the OpenAPIA version
     */
    private function validateOpenAPIAVersion($version): void
    {
        if (!is_string($version)) {
            $this->errors[] = "openapia version must be a string";
            return;
        }
        
        if (!str_starts_with($version, '0.1')) {
            $this->warnings[] = "Version {$version} may not be supported";
        }
    }
    
    /**
     * Validate the info section
     */
    private function validateInfo(array $info): void
    {
        $requiredFields = ['title', 'version', 'description'];
        
        foreach ($requiredFields as $field) {
            if (!array_key_exists($field, $info)) {
                $this->errors[] = "Missing required field in info: {$field}";
            }
        }
        
        if (isset($info['ai_metadata'])) {
            $this->validateAIMetadata($info['ai_metadata']);
        }
    }
    
    /**
     * Validate AI-specific metadata
     */
    private function validateAIMetadata(array $metadata): void
    {
        if (!array_key_exists('domain', $metadata)) {
            $this->warnings[] = "ai_metadata.domain is recommended";
        }
        
        if (isset($metadata['complexity'])) {
            $validComplexities = ['low', 'medium', 'high'];
            if (!in_array($metadata['complexity'], $validComplexities)) {
                $this->errors[] = "Invalid complexity: {$metadata['complexity']}";
            }
        }
    }
    
    /**
     * Validate the models section
     */
    private function validateModels(array $models): void
    {
        if (!is_array($models)) {
            $this->errors[] = "models must be an array";
            return;
        }
        
        if (empty($models)) {
            $this->errors[] = "At least one model is required";
            return;
        }
        
        $modelIds = [];
        foreach ($models as $index => $model) {
            if (!is_array($model)) {
                $this->errors[] = "Model {$index} must be an array";
                continue;
            }
            
            // Validate required fields
            $requiredFields = ['id', 'type', 'provider', 'name', 'purpose'];
            foreach ($requiredFields as $field) {
                if (!array_key_exists($field, $model)) {
                    $this->errors[] = "Model {$index} missing required field: {$field}";
                }
            }
            
            // Check for duplicate IDs
            if (isset($model['id'])) {
                if (in_array($model['id'], $modelIds)) {
                    $this->errors[] = "Duplicate model ID: {$model['id']}";
                }
                $modelIds[] = $model['id'];
            }
            
            // Validate model type
            if (isset($model['type'])) {
                $validTypes = ['LLM', 'Vision', 'Audio', 'Multimodal', 'Classification', 'Embedding'];
                if (!in_array($model['type'], $validTypes)) {
                    $this->warnings[] = "Unknown model type: {$model['type']}";
                }
            }
        }
    }
    
    /**
     * Validate the prompts section
     */
    private function validatePrompts(array $prompts): void
    {
        if (!is_array($prompts)) {
            $this->errors[] = "prompts must be an array";
            return;
        }
        
        $promptIds = [];
        foreach ($prompts as $index => $prompt) {
            if (!is_array($prompt)) {
                $this->errors[] = "Prompt {$index} must be an array";
                continue;
            }
            
            // Validate required fields
            $requiredFields = ['id', 'role', 'template'];
            foreach ($requiredFields as $field) {
                if (!array_key_exists($field, $prompt)) {
                    $this->errors[] = "Prompt {$index} missing required field: {$field}";
                }
            }
            
            // Check for duplicate IDs
            if (isset($prompt['id'])) {
                if (in_array($prompt['id'], $promptIds)) {
                    $this->errors[] = "Duplicate prompt ID: {$prompt['id']}";
                }
                $promptIds[] = $prompt['id'];
            }
            
            // Validate role
            if (isset($prompt['role'])) {
                $validRoles = ['system', 'user', 'assistant'];
                if (!in_array($prompt['role'], $validRoles)) {
                    $this->errors[] = "Invalid prompt role: {$prompt['role']}";
                }
            }
        }
    }
    
    /**
     * Validate the constraints section
     */
    private function validateConstraints(array $constraints): void
    {
        if (!is_array($constraints)) {
            $this->errors[] = "constraints must be an array";
            return;
        }
        
        $constraintIds = [];
        foreach ($constraints as $index => $constraint) {
            if (!is_array($constraint)) {
                $this->errors[] = "Constraint {$index} must be an array";
                continue;
            }
            
            // Validate required fields
            $requiredFields = ['id', 'rule', 'severity'];
            foreach ($requiredFields as $field) {
                if (!array_key_exists($field, $constraint)) {
                    $this->errors[] = "Constraint {$index} missing required field: {$field}";
                }
            }
            
            // Check for duplicate IDs
            if (isset($constraint['id'])) {
                if (in_array($constraint['id'], $constraintIds)) {
                    $this->errors[] = "Duplicate constraint ID: {$constraint['id']}";
                }
                $constraintIds[] = $constraint['id'];
            }
            
            // Validate severity
            if (isset($constraint['severity'])) {
                $validSeverities = ['low', 'medium', 'high', 'critical'];
                if (!in_array($constraint['severity'], $validSeverities)) {
                    $this->errors[] = "Invalid constraint severity: {$constraint['severity']}";
                }
            }
        }
    }
    
    /**
     * Validate the tasks section
     */
    private function validateTasks(array $tasks): void
    {
        if (!is_array($tasks)) {
            $this->errors[] = "tasks must be an array";
            return;
        }
        
        $taskIds = [];
        foreach ($tasks as $index => $task) {
            if (!is_array($task)) {
                $this->errors[] = "Task {$index} must be an array";
                continue;
            }
            
            // Validate required fields
            $requiredFields = ['id', 'description'];
            foreach ($requiredFields as $field) {
                if (!array_key_exists($field, $task)) {
                    $this->errors[] = "Task {$index} missing required field: {$field}";
                }
            }
            
            // Check for duplicate IDs
            if (isset($task['id'])) {
                if (in_array($task['id'], $taskIds)) {
                    $this->errors[] = "Duplicate task ID: {$task['id']}";
                }
                $taskIds[] = $task['id'];
            }
        }
    }
    
    /**
     * Validate the context section
     */
    private function validateContext(array $context): void
    {
        if (!is_array($context)) {
            $this->errors[] = "context must be an array";
            return;
        }
        
        if (!array_key_exists('memory', $context)) {
            $this->warnings[] = "context.memory is recommended";
        }
    }
    
    /**
     * Validate the evaluation section
     */
    private function validateEvaluation(array $evaluation): void
    {
        if (!is_array($evaluation)) {
            $this->errors[] = "evaluation must be an array";
            return;
        }
        
        if (!array_key_exists('metrics', $evaluation)) {
            $this->warnings[] = "evaluation.metrics is recommended";
        }
    }
    
    /**
     * Perform cross-validation between sections
     */
    private function crossValidate(array $spec): void
    {
        // Validate that referenced models exist
        if (isset($spec['tasks']) && isset($spec['models'])) {
            $modelIds = [];
            foreach ($spec['models'] as $model) {
                if (isset($model['id'])) {
                    $modelIds[] = $model['id'];
                }
            }
            
            foreach ($spec['tasks'] as $task) {
                if (isset($task['steps'])) {
                    foreach ($task['steps'] as $step) {
                        if (isset($step['model']) && !in_array($step['model'], $modelIds)) {
                            $this->errors[] = "Task references unknown model: {$step['model']}";
                        }
                    }
                }
            }
        }
        
        // Validate that referenced prompts exist
        if (isset($spec['tasks']) && isset($spec['prompts'])) {
            $promptIds = [];
            foreach ($spec['prompts'] as $prompt) {
                if (isset($prompt['id'])) {
                    $promptIds[] = $prompt['id'];
                }
            }
            
            foreach ($spec['tasks'] as $task) {
                if (isset($task['steps'])) {
                    foreach ($task['steps'] as $step) {
                        if (isset($step['prompt']) && !in_array($step['prompt'], $promptIds)) {
                            $this->errors[] = "Task references unknown prompt: {$step['prompt']}";
                        }
                    }
                }
            }
        }
    }
    
    /**
     * Get list of validation errors
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
    
    /**
     * Get list of validation warnings
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }
    
    /**
     * Print validation results
     */
    public function printResults(): void
    {
        if (!empty($this->errors)) {
            echo "❌ Validation Errors:\n";
            foreach ($this->errors as $error) {
                echo "  - {$error}\n";
            }
        }
        
        if (!empty($this->warnings)) {
            echo "⚠️  Validation Warnings:\n";
            foreach ($this->warnings as $warning) {
                echo "  - {$warning}\n";
            }
        }
        
        if (empty($this->errors) && empty($this->warnings)) {
            echo "✅ Validation passed with no issues\n";
        } elseif (empty($this->errors)) {
            echo "✅ Validation passed with warnings\n";
        }
    }
    
    /**
     * Get validation results as array
     */
    public function getResults(): array
    {
        return [
            'valid' => empty($this->errors),
            'errors' => $this->errors,
            'warnings' => $this->warnings
        ];
    }
}
