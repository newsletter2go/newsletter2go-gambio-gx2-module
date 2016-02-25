version = 0_0_00
outfile = Gambio_nl2go_$(version).zip

$(version): $(outfile)

$(outfile):

	zip -r  build.zip ./gx2/*
	mv build.zip $(outfile)

.PHONY: svn
svn:
	cp -r src/* svn/trunk ; \
	cp -r assets/* svn/assets

.PHONY: clean
clean:
	rm -rf tmp
