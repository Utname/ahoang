<?php

class FtpConnector
{
	/** @var resource|bool */
	protected $ftpConnection = FALSE;


	/**
	 * @param string $host
	 * @param string $user
	 * @param string $password
	 */
	public function __construct(protected string $host, protected string $user, protected string $password)
	{
	}


	/**
	 * @return bool|false|resource
	 */
	protected function getFtpConnection()
	{
		if(!$this->ftpConnection){
			$this->ftpConnection = ftp_connect($this->host);
			$login = @ftp_login($this->ftpConnection, $this->user, $this->password);
			if((!$this->ftpConnection) || (!$login)){
				$this->ftpConnection = FALSE;
			}
			else
			{
				ftp_pasv($this->ftpConnection, true);
			}
		}
		return $this->ftpConnection;
	}


	/**
	 * @param string $filePath
	 * @param string $remoteFilePath
	 * @return bool
	 */
	public function ftpPutFile(string $filePath, string $remoteFilePath): bool
	{
		if(!$this->getFtpConnection()){
			return FALSE;
		}
		return ftp_put($this->getFtpConnection(), $remoteFilePath, $filePath, FTP_BINARY);
	}


	/**
	 * @param string $remoteFilePath
	 * @param string $filePath
	 * @return bool
	 */
	public function ftpGetFile(string $remoteFilePath, string $filePath, $rewrite = FALSE): bool
	{
		if(!$this->getFtpConnection()){
			return FALSE;
		}

		if($rewrite == FALSE && file_exists($filePath)){
			return FALSE;
		}

		return ftp_get($this->getFtpConnection(), $filePath, $remoteFilePath, FTP_BINARY);
	}


	/**
	 * @param string $remoteFilePath
	 * @param string $filePath
	 * @param $rewrite
	 * @return int
	 */
	public function ftpGetFiles(string $remoteFilePath, string $filePath, $rewrite = FALSE): int
	{
		if(!$this->getFtpConnection()){
			return FALSE;
		}

		$counter = 0;
		$list = ftp_nlist($this->getFtpConnection(), $remoteFilePath);
		if(!empty($list)){
			foreach($list as $listFilePath){
				$fileName = pathinfo($listFilePath, PATHINFO_BASENAME);
				if(@$this->ftpGetFile($listFilePath, $filePath.'/'.$fileName, $rewrite)){
					$counter++;
				}
			}
		}
		return $counter;
	}


	/**
	 * @param string $remoteFilePath
	 * @return bool
	 */
	public function ftpFileExists(string $remoteFilePath): bool
	{
		if(!$this->getFtpConnection()){
			return FALSE;
		}
		return ftp_size($this->getFtpConnection(), $remoteFilePath) > -1;
	}


	/**
	 * @param string $remoteFilePath
	 * @return bool
	 */
	public function ftpMakeDir(string $remoteFilePath): bool
	{
		if(!$this->getFtpConnection()){
			return FALSE;
		}
		return ftp_mkdir($this->getFtpConnection(), $remoteFilePath);
	}


	/**
	 * @param string $remoteFilePath
	 * @return bool
	 */
	public function ftpDelete(string $remoteFilePath): bool
	{
		if(!$this->getFtpConnection()){
			return FALSE;
		}
		return ftp_delete($this->getFtpConnection(), $remoteFilePath);
	}
}
