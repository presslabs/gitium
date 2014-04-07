<?php
class Git_Wrapper {
	function __construct($repo_dir) {
		$this->repo_dir = $repo_dir;
	}

	function add() {
		$paths = func_get_args();
		foreach ($paths as $path) {
			exec("cd $this->repo_dir ; git add $path", $response, $return);
			_log($response, $return);
		}
	}

	function commit($message) {
		$message = escapeshellarg($message);
		exec("cd $this->repo_dir ; git commit -m $message", $response, $return);
		_log($response, $return);
	}

	function push($repo, $branch) {
		exec("cd $this->repo_dir ; git push $repo $branch", $response, $return);
		_log($response, $return);
	}
}

$git = new Git_Wrapper('/Users/calin/work/mywp');
