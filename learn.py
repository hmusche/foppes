

# Load libraries
import pandas
import numpy

from sklearn import model_selection
from sklearn.metrics import classification_report
from sklearn.metrics import confusion_matrix
from sklearn.metrics import accuracy_score
from sklearn.linear_model import LogisticRegression
from sklearn.tree import DecisionTreeClassifier
from sklearn.neighbors import KNeighborsClassifier
from sklearn.discriminant_analysis import LinearDiscriminantAnalysis
from sklearn.naive_bayes import GaussianNB
from sklearn.svm import SVC

names = ['hmgw', 'hmgd', 'hmgl', 'hmgs', 'hmgt', 'hstr', 'hppg', 'htsg', 'htss', 'amgw', 'amgd', 'amgl', 'amgs', 'amgt', 'astr', 'appg', 'atsg', 'atss', 'posdiff', 'md', 'res']

dataset = pandas.read_csv('data.csv', names=names)
data_array = dataset.values

input = data_array[:,0:19]
goals = data_array[:,20]

seed = 7
validation_size = 0.2
scoring = 'accuracy'
input_train, input_validation, train, validation = model_selection.train_test_split(input, goals, test_size=validation_size, random_state=seed)




lda = LinearDiscriminantAnalysis()
lda.fit(input_train, train)

bet_dataset = pandas.read_csv('validation.csv', names=names)
bet_array = bet_dataset.values
data = bet_array[:,0:19]

bets = lda.predict(data);


results = numpy.column_stack((bets))

numpy.savetxt("results.txt", results.astype(int), fmt='%i', delimiter=",")
