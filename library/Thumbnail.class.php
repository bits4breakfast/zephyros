<?php
class Thumbnail {
	private $source = "";
	private $maxWidth = 100;
	private $maxHeight = 100;

	public function setSource( $source ) {
		$this->source = $source;
	}

	public function setMaxSize ( $maxWidth = 100, $maxHeight = 100 ) {
		$this->maxWidth = $maxWidth;
		$this->maxHeight = $maxHeight;
	}
	
	private function getImageData() {
		$temp = getImageSize( $this->source );
		
		$fileType = "jpg";
		switch ( $temp[2] ) {
			case 1:
				$fileType = 'gif';
				break;
			case 2:
				$fileType = 'jpg';
				break;
			case 3:
				$fileType = 'png';
				break;
		}
		return array("width"=>$temp[0],"height"=>$temp[1],"type"=>$fileType);		
	}
	
	private function getThumbData() {
		$imgData = $this->getImageData();
		$wRatio = $this->maxWidth  / $imgData['width'];
		$hRatio = $this->maxHeight / $imgData['height'];

		if (($this->maxHeight > $imgData['height'])  && ($this->maxWidth > $imgData['width'])) {
			$height = $imgData['height'];
			$width = $imgData['width'];
		} else {
			if ( $hRatio < $wRatio ) {
				$height = $this->maxHeight;
				$width = round( $imgData['width'] * $hRatio, 0);
			} else {
				$width = $this->maxWidth;
				$height = round( $imgData['height'] * $wRatio, 0);
			}
		}
		return array('width'=>$width,'height'=>$height);
	}

	public function create( $dest ) {
		$thumbData = $this->getThumbData();
		$imgData = $this->getImageData();
		
		$rImg = imageCreateTrueColor ( $thumbData['width'], $thumbData['height'] );

		switch ( $imgData['type'] ) {
			case 'gif':
				$imgSrc = imageCreateFromGIF ( $this->source );
				break;
			case 'jpg':
				$imgSrc = imageCreateFromJPEG ( $this->source );
				break;
			case 'png':
				$imgSrc = imageCreateFromPNG ( $this->source );
				break;
		}

		imageCopyResampled( $rImg, $imgSrc, 0, 0, 0, 0, $thumbData['width'], $thumbData['height'], $imgData['width'], $imgData['height'] );

		switch ( $imgData['type'] ) {
			case 'gif':
				if ( empty( $dest ) ) {
					header( "Content-type: image/gif" );
					return imageGIF( $rImg );
				} else {
					return imageGIF( $rImg, $dest );
				}
				break;

			case 'jpg':
				if ( empty( $dest ) ) {
					header ( "Content-type: image/jpeg" );
					return imageJPEG($rImg,'',100);
				} else {
					return imageJPEG($rImg,$dest,100);
				}
				break;

			case 'png':
				if ( empty( $dest ) ) {
					header ( "Content-type: image/png" );
					return imagePNG( $rImg );
				} else {
					return imagePNG( $rImg, $dest );
				}
				break;
		}
	}
}
?>