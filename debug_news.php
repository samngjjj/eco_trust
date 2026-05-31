<?php
$pythonExe = trim(shell_exec('where python'));
$scriptPath = __DIR__ . '/news_nlp.py';
$search_term = "台積電 2330 ESG";
$cmd = escapeshellarg($pythonExe) . ' ' . escapeshellarg($scriptPath) . ' ' . escapeshellarg($search_term) . ' 2>&1';
echo "Python Exe: $pythonExe\n";
echo "Command: $cmd\n";
echo "Output: " . shell_exec($cmd);
