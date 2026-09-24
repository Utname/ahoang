<?php


class FileIconFilter
{
	public function process(string $fileName): string
	{
		$ext = pathinfo($fileName, PATHINFO_EXTENSION);
		$ext = \Nette\Utils\Strings::lower($ext);

		$icon = 'fa-file';
		if(in_array($ext, ['xls', 'xlsm', 'xlsx', 'xlsb', 'xltx', 'xlt', 'xml'])){
			$icon = 'fa-file-excel';
		}
		elseif(in_array($ext, ['zip', 'rar'])){
			$icon = 'fa-file-archive';
		}
		elseif(in_array($ext, ['mp3', 'm4a', 'wav'])){
			$icon = 'fa-file-audio';
		}
		elseif(in_array($ext, ['jpeg', 'jpg', 'png', 'gif', 'svg', 'psd', 'tiff'])){
			$icon = 'fa-file-image';
		}
		elseif(in_array($ext, ['pdf'])){
			$icon = 'fa-file-pdf';
		}
		elseif(in_array($ext, ['doc', 'docm', 'docx', 'txt'])){
			$icon = 'fa-file-word';
		}
		elseif(in_array($ext, ['mp4', 'mov', 'avi', 'flv', 'mkv'])){
			$icon = 'fa-file-video';
		}

		return $icon;
	}
}
