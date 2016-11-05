
The default storescp coming with dcmtk 3.6.0 was changed. The executable is now writing directly into the pipe
instead of calling an external shell first. The executable is also not running fork for each pipe write operation.
This keeps the number of threads fixed. The storescp receiver will slow down if images cannot be processed immediately
by processSingleFile.py.

The changes required to make storescp compile on FIONA and the changes required to change its behavior are documented
in the diff below.

Hauke (Nov 2016)


diff -r dcmtk-3.6.0/dcmimage/include/dcmtk/dcmimage/diargpxt.h dcmtk-3.6.0_changed/dcmimage/include/dcmtk/dcmimage/diargpxt.h
70c70
<             convert(OFstatic_cast(const T1 *, pixel->getData()) + pixel->getPixelStart(), palette, planeSize, bits);
---
>             this->convert(OFstatic_cast(const T1 *, pixel->getData()) + pixel->getPixelStart(), palette, planeSize, bits);
94c94
<         if (Init(pixel))
---
>         if (this->Init(pixel))
diff -r dcmtk-3.6.0/dcmimage/include/dcmtk/dcmimage/dicmypxt.h dcmtk-3.6.0_changed/dcmimage/include/dcmtk/dcmimage/dicmypxt.h
68c68
<             convert(OFstatic_cast(const T1 *, pixel->getData()) + pixel->getPixelStart(), planeSize, bits);
---
>             this->convert(OFstatic_cast(const T1 *, pixel->getData()) + pixel->getPixelStart(), planeSize, bits);
90c90
<         if (Init(pixel))
---
>         if (this->Init(pixel))
diff -r dcmtk-3.6.0/dcmimage/include/dcmtk/dcmimage/dicocpt.h dcmtk-3.6.0_changed/dcmimage/include/dcmtk/dcmimage/dicocpt.h
89c89
<         if (Init(pixel))
---
>         if (this->Init(pixel))
diff -r dcmtk-3.6.0/dcmimage/include/dcmtk/dcmimage/dicoflt.h dcmtk-3.6.0_changed/dcmimage/include/dcmtk/dcmimage/dicoflt.h
101c101
<         if (Init(pixel))
---
>         if (this->Init(pixel))
104c104
<                 flipHorzVert(pixel, this->Data);
---
>                 this->flipHorzVert(pixel, this->Data);
106c106
<                 flipHorz(pixel, this->Data);
---
>                 this->flipHorz(pixel, this->Data);
108c108
<                 flipVert(pixel, this->Data);
---
>                 this->flipVert(pixel, this->Data);
diff -r dcmtk-3.6.0/dcmimage/include/dcmtk/dcmimage/dicorot.h dcmtk-3.6.0_changed/dcmimage/include/dcmtk/dcmimage/dicorot.h
101c101
<         if (Init(pixel))
---
>         if (this->Init(pixel))
104c104
<                 rotateRight(pixel, this->Data);
---
>                 this->rotateRight(pixel, this->Data);
106c106
<                 rotateTopDown(pixel, this->Data);
---
>                 this->rotateTopDown(pixel, this->Data);
108c108
<                 rotateLeft(pixel, this->Data);
---
>                 this->rotateLeft(pixel, this->Data);
diff -r dcmtk-3.6.0/dcmimage/include/dcmtk/dcmimage/dicosct.h dcmtk-3.6.0_changed/dcmimage/include/dcmtk/dcmimage/dicosct.h
110,111c110,111
<         if (Init(pixel))
<             scaleData(pixel, this->Data, interpolate);
---
>         if (this->Init(pixel))
>             this->scaleData(pixel, this->Data, interpolate);
diff -r dcmtk-3.6.0/dcmimage/include/dcmtk/dcmimage/dihsvpxt.h dcmtk-3.6.0_changed/dcmimage/include/dcmtk/dcmimage/dihsvpxt.h
68c68
<             convert(OFstatic_cast(const T1 *, pixel->getData()) + pixel->getPixelStart(), planeSize, bits);
---
>             this->convert(OFstatic_cast(const T1 *, pixel->getData()) + pixel->getPixelStart(), planeSize, bits);
90c90
<         if (Init(pixel))
---
>         if (this->Init(pixel))
diff -r dcmtk-3.6.0/dcmimage/include/dcmtk/dcmimage/dipalpxt.h dcmtk-3.6.0_changed/dcmimage/include/dcmtk/dcmimage/dipalpxt.h
74c74
<                 convert(OFstatic_cast(const T1 *, pixel->getData()) + pixel->getPixelStart(), palette);
---
>                 this->convert(OFstatic_cast(const T1 *, pixel->getData()) + pixel->getPixelStart(), palette);
95c95
<         if (Init(pixel))
---
>         if (this->Init(pixel))
diff -r dcmtk-3.6.0/dcmimage/include/dcmtk/dcmimage/dirgbpxt.h dcmtk-3.6.0_changed/dcmimage/include/dcmtk/dcmimage/dirgbpxt.h
68c68
<             convert(OFstatic_cast(const T1 *, pixel->getData()) + pixel->getPixelStart(), planeSize, bits);
---
>             this->convert(OFstatic_cast(const T1 *, pixel->getData()) + pixel->getPixelStart(), planeSize, bits);
90c90
<         if (Init(pixel))
---
>         if (this->Init(pixel))
diff -r dcmtk-3.6.0/dcmimage/include/dcmtk/dcmimage/diybrpxt.h dcmtk-3.6.0_changed/dcmimage/include/dcmtk/dcmimage/diybrpxt.h
70c70
<             convert(OFstatic_cast(const T1 *, pixel->getData()) + pixel->getPixelStart(), planeSize, bits, rgb);
---
>             this->convert(OFstatic_cast(const T1 *, pixel->getData()) + pixel->getPixelStart(), planeSize, bits, rgb);
94c94
<         if (Init(pixel))
---
>         if (this->Init(pixel))
diff -r dcmtk-3.6.0/dcmimage/include/dcmtk/dcmimage/diyf2pxt.h dcmtk-3.6.0_changed/dcmimage/include/dcmtk/dcmimage/diyf2pxt.h
75c75
<                 convert(OFstatic_cast(const T1 *, pixel->getData()) + pixel->getPixelStart(), bits, rgb);
---
>                 this->convert(OFstatic_cast(const T1 *, pixel->getData()) + pixel->getPixelStart(), bits, rgb);
98c98
<         if (Init(pixel))
---
>         if (this->Init(pixel))
diff -r dcmtk-3.6.0/dcmimage/include/dcmtk/dcmimage/diyp2pxt.h dcmtk-3.6.0_changed/dcmimage/include/dcmtk/dcmimage/diyp2pxt.h
73c73
<                 convert(OFstatic_cast(const T1 *, pixel->getData()) + pixel->getPixelStart(), bits);
---
>                 this->convert(OFstatic_cast(const T1 *, pixel->getData()) + pixel->getPixelStart(), bits);
94c94
<         if (Init(pixel))
---
>         if (this->Init(pixel))
diff -r dcmtk-3.6.0/dcmimgle/include/dcmtk/dcmimgle/diflipt.h dcmtk-3.6.0_changed/dcmimgle/include/dcmtk/dcmimgle/diflipt.h
126c126
<                 flipHorzVert(src, dest);
---
>                 this->flipHorzVert(src, dest);
128c128
<                 flipHorz(src, dest);
---
>                 this->flipHorz(src, dest);
130c130
<                 flipVert(src, dest);
---
>                 this->flipVert(src, dest);
132c132
<                 copyPixel(src, dest);
---
>                 this->copyPixel(src, dest);
diff -r dcmtk-3.6.0/dcmimgle/include/dcmtk/dcmimgle/dimoflt.h dcmtk-3.6.0_changed/dcmimgle/include/dcmtk/dcmimgle/dimoflt.h
77c77
<                 flip(OFstatic_cast(const T *, pixel->getData()), horz, vert);
---
>                 this->flip(OFstatic_cast(const T *, pixel->getData()), horz, vert);
109c109
<                     flipHorzVert(&pixel, &this->Data);
---
>                     this->flipHorzVert(&pixel, &this->Data);
111c111
<                     flipHorz(&pixel, &this->Data);
---
>                     this->flipHorz(&pixel, &this->Data);
113c113
<                     flipVert(&pixel, &this->Data);
---
>                     this->flipVert(&pixel, &this->Data);
diff -r dcmtk-3.6.0/dcmimgle/include/dcmtk/dcmimgle/dimoipxt.h dcmtk-3.6.0_changed/dcmimgle/include/dcmtk/dcmimgle/dimoipxt.h
79c79
<                 determineMinMax(OFstatic_cast(T3, this->Modality->getMinValue()), OFstatic_cast(T3, this->Modality->getMaxValue()));
---
>                 this->determineMinMax(OFstatic_cast(T3, this->Modality->getMinValue()), OFstatic_cast(T3, this->Modality->getMaxValue()));
82c82
<                 determineMinMax(OFstatic_cast(T3, this->Modality->getMinValue()), OFstatic_cast(T3, this->Modality->getMaxValue()));
---
>                 this->determineMinMax(OFstatic_cast(T3, this->Modality->getMinValue()), OFstatic_cast(T3, this->Modality->getMaxValue()));
diff -r dcmtk-3.6.0/dcmimgle/include/dcmtk/dcmimgle/dimorot.h dcmtk-3.6.0_changed/dcmimgle/include/dcmtk/dcmimgle/dimorot.h
78c78
<                 rotate(OFstatic_cast(const T *, pixel->getData()), degree);
---
>                 this->rotate(OFstatic_cast(const T *, pixel->getData()), degree);
108c108
<                     rotateRight(&pixel, &(this->Data));
---
>                     this->rotateRight(&pixel, &(this->Data));
110c110
<                     rotateTopDown(&pixel, &(this->Data));
---
>                     this->rotateTopDown(&pixel, &(this->Data));
112c112
<                     rotateLeft(&pixel, &(this->Data));
---
>                     this->rotateLeft(&pixel, &(this->Data));
diff -r dcmtk-3.6.0/dcmimgle/include/dcmtk/dcmimgle/dimosct.h dcmtk-3.6.0_changed/dcmimgle/include/dcmtk/dcmimgle/dimosct.h
91c91
<                 scale(OFstatic_cast(const T *, pixel->getData()), pixel->getBits(), interpolate, pvalue);
---
>                 this->scale(OFstatic_cast(const T *, pixel->getData()), pixel->getBits(), interpolate, pvalue);
127c127
<                 scaleData(&pixel, &this->Data, interpolate, value);
---
>                 this->scaleData(&pixel, &this->Data, interpolate, value);
diff -r dcmtk-3.6.0/dcmimgle/include/dcmtk/dcmimgle/dirotat.h dcmtk-3.6.0_changed/dcmimgle/include/dcmtk/dcmimgle/dirotat.h
129c129
<             rotateRight(src, dest);
---
>             this->rotateRight(src, dest);
131c131
<             rotateTopDown(src, dest);
---
>             this->rotateTopDown(src, dest);
133c133
<             rotateLeft(src, dest);
---
>             this->rotateLeft(src, dest);
135c135
<             copyPixel(src, dest);
---
>             this->copyPixel(src, dest);
diff -r dcmtk-3.6.0/dcmimgle/include/dcmtk/dcmimgle/discalet.h dcmtk-3.6.0_changed/dcmimgle/include/dcmtk/dcmimgle/discalet.h
209c209
<                 fillPixel(dest, value);                                               // ... fill bitmap
---
>                 this->fillPixel(dest, value);                                               // ... fill bitmap
214c214
<                     copyPixel(src, dest);                                             // copying
---
>                     this->copyPixel(src, dest);                                             // copying
217c217
<                     clipPixel(src, dest);                                             // clipping
---
>                     this->clipPixel(src, dest);                                             // clipping
219c219
<                     clipBorderPixel(src, dest, value);                                // clipping (with border)
---
>                     this->clipBorderPixel(src, dest, value);                                // clipping (with border)
225c225
<                 bicubicPixel(src, dest);                                              // bicubic magnification
---
>                 this->bicubicPixel(src, dest);                                              // bicubic magnification
228c228
<                 bilinearPixel(src, dest);                                             // bilinear magnification
---
>                 this->bilinearPixel(src, dest);                                             // bilinear magnification
230c230
<                 expandPixel(src, dest);                                               // interpolated expansion (c't)
---
>                 this->expandPixel(src, dest);                                               // interpolated expansion (c't)
232c232
<                 reducePixel(src, dest);                                               // interpolated reduction (c't)
---
>                 this->reducePixel(src, dest);                                               // interpolated reduction (c't)
234c234
<                 interpolatePixel(src, dest);                                          // interpolation (pbmplus), fallback
---
>                 this->interpolatePixel(src, dest);                                          // interpolation (pbmplus), fallback
236c236
<                 replicatePixel(src, dest);                                            // replication
---
>                 this->replicatePixel(src, dest);                                            // replication
238c238
<                 suppressPixel(src, dest);                                             // supression
---
>                 this->suppressPixel(src, dest);                                             // supression
240c240
<                 scalePixel(src, dest);                                                // general scaling
---
>                 this->scalePixel(src, dest);                                                // general scaling
570c570
<             clearPixel(dest);
---
>             this->clearPixel(dest);
908c908
<             clearPixel(dest);
---
>             this->clearPixel(dest);
1032c1032
<             clearPixel(dest);
---
>             this->clearPixel(dest);
diff -r dcmtk-3.6.0/dcmnet/apps/storescp.cc dcmtk-3.6.0_changed/dcmnet/apps/storescp.cc
36a37,41
> #include <libgen.h>
> #include <sys/types.h>
> #include <fcntl.h>
> #include <sys/stat.h>
> 
2301a2307,2314
> static void remove_all_chars(char* str, char c) {
>   char *pr = str, *pw = str;
>   while(*pr) {
>     *pw = *pr++;
>     pw += (*pw != c);
>   }
>   *pw = '\0';
> }
2319a2333,2335
>   OFString dir = "";
>   OFString outputFileName = "";
> 
2323c2339
<     OFString dir = (opt_sortStudyMode == ESM_None) ? opt_outputDirectory : subdirectoryPathAndName;
---
>     dir = (opt_sortStudyMode == ESM_None) ? opt_outputDirectory : subdirectoryPathAndName;
2328c2344
<     OFString outputFileName = outputFileNameArray.back();
---
>     outputFileName = outputFileNameArray.back();
2342c2358,2401
<   executeCommand( cmd );
---
>   //  executeCommand( cmd );
> 
>     //  INSTEAD:
>     //  Calling executeCommand creates a shell process that will zombie eventually.
>     //  Many zombies will prevent files from getting created, instead try to write to the pipe directly.
>     //  Instead we should just connect directly to the pipe for processing.
>     //  This is a special solution for FIONA only
>     //  (Hauke Nov 2016)
>     //
>     int fpp;
>     const char *pipefile = "/tmp/.processSingleFilePipe";
> 
>     //OFString workingdir = "/data/site/.arrived";
>     char *bname = strdup(dir.c_str());
> 
>     char *calling = strdup(callingAETitle.c_str());
>     char *called = strdup(calledAETitle.c_str());
>     char *address = strdup(callingPresentationAddress.c_str());
>     remove_all_chars(calling, '"');
>     remove_all_chars(called, '"');
>     remove_all_chars(address, '"');
> 
>     OFString fname = "/data/site/.arrived/" + OFString(calling) + " " + OFString(called) + " " + OFString(address) + " " + basename(bname);
> 
>     //printf("Write now: %s\n", fname.c_str());
>     int fp = open(fname.c_str(),O_WRONLY|O_CREAT|O_NOCTTY|O_NONBLOCK,0666);
>     if (fp>=0) {
>        utimensat(AT_FDCWD, fname.c_str(),NULL,0);
>     } 
>     close(fp);
> 
>     OFString pipecontents = OFString(calling) + "," + OFString(called) + "," + OFString(address) + "," + dir + "," + outputFileName + "\n";
> 
>     //printf("\nWrite to pipe: %s\n\n", pipecontents.c_str());
> 
>     fpp = open(pipefile, O_WRONLY);
>     if (fpp < 0) {
>       printf("ERROR: could not write to the PIPE at %s\n", pipefile);
>     } else {
>       write(fpp, pipecontents.c_str(), strlen(pipecontents.c_str())+1);
>       close(fpp);
>     }
> 
> 
2482a2542,2543
> 
>   // can we get rid of double quotes as well? (Hauke)
diff -r dcmtk-3.6.0/ofstd/include/dcmtk/ofstd/ofoset.h dcmtk-3.6.0_changed/ofstd/include/dcmtk/ofstd/ofoset.h
149c149
<           Resize( this->size * 2 );
---
>           this->Resize( this->size * 2 );
192c192
<             Resize( this->size * 2 );
---
>             this->Resize( this->size * 2 );

