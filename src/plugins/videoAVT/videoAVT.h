/*-----------------------------------------------------------------

    GEM - Graphics Environment for Multimedia

    video backend for Gem

    Copyright (c) 2010 IOhannes m zmoelnig. forum::f�r::uml�ute. IEM. zmoelnig@iem.kug.ac.at
    For information on usage and redistribution, and for a DISCLAIMER OF ALL
    WARRANTIES, see the file, "GEM.LICENSE.TERMS" in this distribution.

    Linux version by Miller Puckette. msp@ucsd.edu
	
-----------------------------------------------------------------*/

#ifndef INCLUDE_VIDEOAVT_H_
#define INCLUDE_VIDEOAVT_H_

#include "plugins/video.h"

#if defined HAVE_LIBAVT
# define HAVE_AVT
#endif

#ifdef HAVE_AVT
#include "PvApi.h"
#endif
/*-----------------------------------------------------------------
-------------------------------------------------------------------
CLASS
	videoAVT
    
    Grabs in a video
    
KEYWORDS
    pix
    
DESCRIPTION

    support for the "AVT GiGE SDK" by Prosilica
    
-----------------------------------------------------------------*/
namespace gem { class GEM_EXPORT videoAVT : public video {
    public:
        //////////
        // Constructor
    	videoAVT(void);
    	    	
    	//////////
    	// Destructor
    	virtual ~videoAVT();

#ifdef HAVE_AVT
	////////
	// open the video-device
	virtual bool           openDevice(void);
	virtual void          closeDevice(void);
    
    	//////////
    	// Start up the video device
    	// [out] int - returns 0 if bad
    	bool	    	startTransfer();
	//////////
    	// Stop the video device
    	// [out] int - returns 0 if bad
    	bool	   	stopTransfer();

	//////////
	// get the next frame
	bool grabFrame();

  virtual std::vector<std::string>enumerate(void);
   
 protected:

#endif /* HAVE_AVT */

}; 
};

#endif	// for header file
