/*-----------------------------------------------------------------

    GEM - Graphics Environment for Multimedia

    Load an video into a pix block

    Copyright (c) 1997-1999 Mark Danks. mark@danks.org
    Copyright (c) G�nther Geiger. geiger@epy.co.at
    Copyright (c) 2001-2002 IOhannes m zmoelnig. forum::f�r::uml�ute. IEM. zmoelnig@iem.kug.ac.at
    For information on usage and redistribution, and for a DISCLAIMER OF ALL
    WARRANTIES, see the file, "GEM.LICENSE.TERMS" in this distribution.

    Linux version by Miller Puckette. msp@ucsd.edu
	
-----------------------------------------------------------------*/

#ifndef INCLUDE_VIDEODC1394_H_
#define INCLUDE_VIDEODC1394_H_
#include "plugins/video.h"


#ifdef HAVE_LIBDC1394
#include "dc1394/dc1394.h"
#endif

#ifdef HAVE_PTHREAD
# include <pthread.h>
#endif


/*-----------------------------------------------------------------
-------------------------------------------------------------------
CLASS
	pix_video
    
    Loads in a video
    
KEYWORDS
    pix
    
DESCRIPTION

    "dimen" (int, int) - set the x,y dimensions
    "zoom" (int, int) - the zoom factor (1.0 is nominal) (num / denom)
    "bright" (int) - the brightnes
    "contrast" (int) - the contrast
    "hue" (int) - the hue
    "sat" (int) - the saturation
    
-----------------------------------------------------------------*/
namespace gem { class GEM_EXTERN videoDC1394 : public video {
    public:
        //////////
        // Constructor
    	videoDC1394(void);
    	    	
    	//////////
    	// Destructor
    	virtual ~videoDC1394();
#ifdef HAVE_LIBDC1394
	////////
	// open the video-device
	virtual bool           openDevice(void);
	virtual void          closeDevice(void);
    
    	//////////
    	// Start up the video device
    	// [out] int - returns 0 if bad
    	bool	    	startTransfer(void);
	//////////
    	// Stop the video device
    	// [out] int - returns 0 if bad
    	bool	   	stopTransfer(void);

	//////////
	// get the next frame
	bool    grabFrame();

	//////////
	// Set the video dimensions
	virtual bool	    	setColor(int);
  virtual bool        setChannel(int chan, float freq);

	//////////
	// get available devices
  virtual std::vector<std::string>enumerate(void);
  
 protected:

  //-----------------------------------
  // GROUP:	Linux specific video data
  //-----------------------------------

  dc1394camera_t * m_dccamera;

  dc1394video_frame_t * m_dcframe;
  dc1394_t * m_dc;

  imageStruct m_frame;

#endif /* HAVE_LIBDC1394 */
};
};

#endif	// for header file