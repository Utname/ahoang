var gulp = require('gulp');
var sass = require('gulp-sass')(require('sass'));
var browserSync = require('browser-sync').create();
var sourcemaps = require('gulp-sourcemaps');

gulp.task('sass', function(){
return gulp.src(['scss/style.scss', 'scss/custom-bootstrap.scss'])
    .pipe(sourcemaps.init())
    .pipe(sass()) // Converts Sass to CSS with gulp-sass
    .pipe(sourcemaps.write('.'))
    .pipe(gulp.dest('css'))
    .pipe(browserSync.stream());
});

gulp.task('serve', gulp.series('sass', function() {
    browserSync.init({
        server: "."
    });
    gulp.watch('scss/*.scss', gulp.series(['sass'])); 
    gulp.watch("*.html").on('change', browserSync.reload);
}))

gulp.task('default', gulp.series('serve'));

